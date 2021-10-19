<?php

require_once 'config.php';
require_once 'settings.php';
require_once 'common_lib.php';
require_once 'modem3g.php';
require_once 'padlock_api.php';
require_once 'lighters_api.php';
require_once 'well_pump_api.php';
require_once 'avreg_lib.php';
require_once 'player_lib.php';

class Guard {
    private $log;

    // Used for debugging alarm system
    private $hide_telegram_msg;
    private $hide_sound;
    private $hide_sms;

    function __construct() {
        $this->log = new Plog("sr90:Guard");
        $this->hide_telegram_msg = is_file("HIDE_TELEGRAM");
        $this->hide_sound = is_file("HIDE_SOUND");
        $this->hide_sms = is_file("HIDE_SMS");
    }

    function tg_info()
    {
        $argv = func_get_args();
        $format = array_shift($argv);
        $msg = vsprintf($format, $argv);

        if (!$this->hide_telegram_msg)
            tn()->send_to_msg($msg);
        else
            tn()->send_to_admin("INFO: %s", $msg);
    }

    function tg_alarm()
    {
        $argv = func_get_args();
        $format = array_shift($argv);
        $msg = vsprintf($format, $argv);

        if (!$this->hide_telegram_msg)
            tn()->send_to_alarm($msg);
        else
            tn()->send_to_admin("ALARM: %s", $msg);
    }

    function zones()
    {
        return conf_guard()['zones'];
    }

    function zone_is_locked($zname)
    {
        foreach (settings_guard()['locked_zones'] as $z)
            if ($z == $zname)
                return true;
        return false;
    }

    function zone_is_ignored($zname)
    {
        foreach ($this->ignored_zones() as $zone)
            if ($zone['name'] == $zname)
                return true;

        return false;
    }

    function locked_zones()
    {
        $list = [];

        foreach ($this->zones() as $zone) {
            if ($this->zone_is_locked($zone['name']))
                $list[] = $zone;
        }

        return $list;
    }

    function unlocked_zones()
    {
        $list = [];

        foreach ($this->zones() as $zone) {
            if (!$this->zone_is_locked($zone['name']))
                $list[] = $zone;
        }
        return $list;
    }

    function not_ready_zones()
    {
        $list = [];
        foreach ($this->unlocked_zones() as $zone) {
            $incorrect_zone = false;
            foreach ($zone['io_sensors'] as $sensor_name => $trig_state) {
                $state = iop($sensor_name)->state();
                if ($state == $trig_state)
                    $incorrect_zone = true;
            }
            if ($incorrect_zone)
                $list[] = $zone;
        }
        return $list;
    }

    function ignored_zones()
    {
        $data = db()->query("SELECT ignore_zones FROM guard_states ORDER by created DESC LIMIT 1");
        if (!$data) {
            $this->log->err("Can't getting ignore_zones from DB");
            return NULL;
        }

        if (! (isset($data['ignore_zones']) && $data['ignore_zones']))
            return [];

        $ignore_zones_names = string_to_array($data['ignore_zones']);
        $list = [];
        foreach ($ignore_zones_names as $zname)
            $list[] = $this->zone_by_name($zname);

        return $list;
    }

    function zones_list_to_text($zones)
    {
        $text = "";
        $sep = '';
        foreach ($zones as $zone) {
            $text .= sprintf("%s%s", $sep, $zone['desc']);
            $sep = ', ';
        }
        return $text;
    }

    function zone_by_sensor_name($sname)
    {
        foreach ($this->zones() as $zone)
            foreach ($zone['io_sensors'] as $sens_name => $active_state)
                if ($sens_name == $sname)
                    return $zone;
        return null;
    }


    function zone_by_name($zname)
    {
        $zones = conf_guard()['zones'];
        foreach ($zones as $zone) {
            if ($zone['name'] == $zname)
                return $zone;
        }
        return null;
    }

    function make_alarm_photos($alarm_id)
    {
        $rc = 0;
        foreach (conf_guard()['video_cameras'] as $cam) {
            $cmd = sprintf('ffmpeg -f video4linux2 -i %s -vf scale=%s -vframes 1 %s/%scam_%d.jpeg',
                           $cam['v4l_dev'], $cam['resolution'],
                           conf_guard()['alarm_snapshot_dir'], $alarm_id, $cam['id']);
            $ret = run_cmd($cmd);
            if ($ret['rc']) {
                $this->log->err("Can't create screenshot for camera %d %s: cmd='%s' response:, %s",
                                $cam['id'], $cam['v4l_dev'], $cmd, $ret['log']);
            }
            $rc |= $ret['rc'];
        }
        return $rc;
    }


    function send_alarm_photos_to_sr38($alarm_id)
    {
        $ret = run_cmd(sprintf('scp %s/%d_*.jpeg stelhs@sr38.org:/storage/www/plato/alarm_img/',
                       conf_guard()['alarm_snapshot_dir'], $alarm_id));
        if ($ret['rc'])
            $this->log->err("Error occured during alarm cam photos sending: %s",
                            $ret['log']);
        return $ret['rc'];
    }


    function send_alarm_photos_to_telegram($alarm_id)
    {
        foreach (conf_guard()['video_cameras'] as $cam) {
            $this->tg_alarm("Камера %d:\n http://sr38.org/plato/alarm_img/%d_cam_%d.jpeg",
                             $cam['id'], $alarm_id, $cam['id']);
        }
    }

    function send_current_photos_to_telegram($chat_id = 0)
    {
        $content = file_get_contents('http://sr38.org/plato/?no_view');
        $ret = json_decode($content, true);
        if ($ret === NULL) {
            $this->tg_info("Не удалось получить изображение с камер: %s",
                            $content);
            $this->log->err("can't getting current photos\n");
            return;
        }
        $photos = $ret;

        foreach ($photos as $cam_num => $file) {
            $msg = sprintf("Камера %d:\n %s", $cam_num, $file);
            if ($chat_id) {
                telegram()->send_message($chat_id, $msg);
                continue;
            }
            $this->tg_info($msg);
        }
    }

    function upload_cam_video($cam, $server_dir, $start_time, $duration, $prefix = "")
    {
        $server_files = [];

        $video_files = avreg()->video_files($start_time, $duration, $cam['name']);
        if (!$video_files || !count($video_files)) {
            $msg = sprintf("Неудалось получить видеофайлы для камеры %s",
                           $cam['name']);
            tn()->send_to_admin($msg);
            $this->log->err("Can't get videos for camera %s\n", $cam['name']);
            return -1;
        }
        $cnt = 0;
        foreach ($video_files as $file) {
            $cnt ++;
            if ($cnt > 15) {
                $this->log->err("To many video files for send\n");
                return -1;
            }
            $server_filename = sprintf("%s_%d_%s", $prefix, $cam['id'], basename($file['file']));

            $cmd = sprintf('scp %s stelhs@sr38.org:/storage/www/plato/%s/%s',
                           $file['file'], $server_dir, $server_filename);
            $ret = run_cmd($cmd);
            if ($ret['rc']) {
                $msg = sprintf("Неудалось загрузить видеофайл %s для камеры %s: %s",
                               $file['file'], $cam['name'], $ret['log']);
                tn()->send_to_admin($msg);
                $this->log->err("Can't upload videos for camera %s: %s\n", $cam['name'], $ret['log']);
                continue;
            }

            $server_files[] = sprintf("http://sr38.org/plato/%s/%s",
                                      $server_dir, $server_filename);
        }
        return $server_files;
    }


    function stat()
    {
        $data = db()->query("SELECT * FROM guard_states ORDER by created DESC LIMIT 1");
        if ($data < 0) {
            $this->log->err("Can't getting last guard status");
            return $data;
        }

        if (!is_array($data) || !isset($data['state'])) {
        	$data = array();
            $data['state'] = 'sleep';
        }

        if (isset($data['user_id']))
            $data['user_name'] = user_by_id($data['user_id'])['name'];

        if (isset($data['ignore_zones']) && $data['ignore_zones'])
            $data['ignore_zones'] = string_to_array($data['ignore_zones']);
        else
            $data['ignore_zones'] = [];

        $data['locked_zones'] = $this->locked_zones();
        return $data;
    }



    function state()
    {
        $row = db()->query("SELECT state FROM guard_states ORDER by created DESC LIMIT 1");
        if (!$row) {
            $this->log->err("Can't getting last guard state");
            return True;
        }

        if (!is_array($row) || !isset($row['state']))
            return NULL;

        return $row['state'];
    }

    function state_id()
    {
        $row = db()->query("SELECT id FROM guard_states ORDER by created DESC LIMIT 1");
        if (!$row) {
            $this->log->err("Can't getting last guard state id");
            return True;
        }

        if (!is_array($row) || !isset($row['id']))
            return NULL;

        return $row['id'];
    }


    function stop($method, $user_id = 0, $with_sms = false)
    {
        if ($this->state() == 'sleep') {
            $this->log->info("Guard already stopped");
            return 'already_stopped';
        }

        if (!$this->hide_sound)
            player_start('sounds/unlock.wav');
        else
            $this->tg_info('Run sound sounds/unlock.wav');

        $user = user_by_id($user_id);
        $user_name = 'кто-то';
        if (is_array($user))
            $user_name = $user['name'];

        iop('sk_power')->up();
        iop('RP_sockets')->up();
        iop('workshop_power')->up();
        padlocks_open();
        gates()->power_enable();

        $state_id = db()->insert('guard_states', ['state' => 'sleep',
                                                  'user_id' => $user_id,
                                                  'method' => $method]);
        if (!$state_id) {
            $this->log->err("Can't stop guard: can't insert to database");
            return 'db_error';
        }

        gates()->open();
        gates()->close_after(60);

        $stat_text = skynet_stat_sms();
        $this->log->info("Guard stoped by %s throught %s", $user_name, $method);

        $this->tg_info("Охрана отключена, отключил %s с помощью %s.",
                        $user_name, $method);
        $this->send_current_photos_to_telegram();

        boiler()->set_room_t(16);

        if ($method == 'cli') {
            pnotice("stat: %s\n", $stat_text);
            return 'ok';
        }

        if ($with_sms && !$this->hide_sms) {
            $text = sprintf("Охрана отключена: %s",
                            $method);
            modem3g()->send_sms_to_user($user_id, $text);
        }

        return 'ok';
    }


    function start($method, $user_id = 0, $with_sms = false)
    {
        if ($this->state() == 'ready') {
            $this->log->info("Guard already started");
            return 'already_started';
        }

        if (!$this->hide_sound)
            player_start('sounds/lock.wav', 55);
        else
            $this->tg_info('Run sound sounds/lock.wav');

        well_pump()->stop();

        iop('sk_power')->down();
        iop('RP_sockets')->down();
        iop('workshop_power')->down();
        padlocks_close();

        $user = user_by_id($user_id);
        $user_name = 'кто-то';
        if (is_array($user))
            $user_name = $user['name'];

        $tg_zone_report = '';
        $locked_zones = $this->locked_zones();
        if (count($locked_zones))
            $tg_zone_report .= sprintf("Заблокированные зоны: %s\n",
                                     $this->zones_list_to_text($locked_zones));

        $not_ready_zones = $this->not_ready_zones();
        if (count($not_ready_zones))
            $tg_zone_report .= sprintf("Не готовые к охране зоны: %s\n",
                              $this->zones_list_to_text($not_ready_zones));

        if (strlen($tg_zone_report))
            $this->tg_info($tg_zone_report);


        $state_id = db()->insert('guard_states',
                                 ['state' => 'ready',
                                  'method' => $method,
                                  'user_id' => $user_id,
                                  'ignore_zones' => $this->zones_list_to_text($not_ready_zones)]);
        if (!$state_id) {
            $this->log->err("Can't start guard: can't insert to database");
            return 'db_error';
        }

        $stat_text = skynet_stat_sms();

        $this->log->info("Guard started by %s throught %s", $user_name, $method);
        $this->tg_info("Охрана включена, включил %s с помощью %s.",
                        $user_name, $method);
        $this->send_current_photos_to_telegram();

        boiler()->set_room_t(5);

        $rc = gates()->close_sync();
        if ($rc)
            $this->tg_info("Возникла неполадка: ворота не закрылись: %d", $rc);
        else
            $this->tg_info("Ворота закрылись");
        gates()->power_disable();

        if ($method == 'cli') {
            pnotice("stat: %s\n", $stat_text);
            return 'ok';
        }

        if ($with_sms && !$this->hide_sms) {
            $text = sprintf("Охрана включена: %s",
                            $method);
            modem3g()->send_sms_to_user($user_id, $text);
        }
        return 'ok';
    }

    function sensor_handler($sname, $state)
    {
        // ignore sensors if guard stopped
        if ($this->state() == 'sleep')
            return;

        $zone = $this->zone_by_sensor_name($sname);
        if (!$zone)
            return 0;

        if ($this->zone_is_locked($zone['name'])) {
            $this->log->info("sensor_handler(): zone %d is locked\n", $zone['name']);
            return 0;
        }

        if ($this->zone_is_ignored($zone['name'])) {
            $this->log->info("sensor_handler(): zone %d is ignored\n", $zone['name']);
            return 0;
        }

        // ignore ALARM if set ready state a little time ago
        $ret = db()->query(sprintf("SELECT id FROM guard_states " .
                                   "WHERE " .
                                       "(created + interval %d second) > now() " .
                                   "ORDER BY created DESC LIMIT 1",
                                    conf_guard()['ready_set_interval']));
        if ($ret < 0)
            $this->log->err("sensor_handler(): Can't MySQL query\n");

        if (isset($ret['id'])) {
            $this->log->info("Alarm was ignored because ready state a little time ago\n");
            return 0;
        }

        // check for activity any sensors in current zone
        $total_cnt = $active_cnt = 0;
        foreach ($zone['io_sensors'] as $sensor => $active_state) {

            // ignore initiated sensor
            if ($sensor == $sname)
                continue;

            $total_cnt ++;
            $ret = db()->query(sprintf('SELECT state, id FROM io_events ' .
                                       'WHERE port_name = "%s" ' .
                                       'ORDER BY id DESC LIMIT 1',
                                       $sname));
            if ($ret < 0)
                $this->log->err("sensor_handler(): Can't MySQL query\n");

            if (!is_array($ret))
                continue;

            if ($ret['state'] != $active_state) {
                $active_cnt ++;
                continue;
            }

            $state_id = $ret['id'];

            // check when it triggered at last 'diff_interval' seconds
            $ret = db()->query(sprintf('SELECT id FROM io_events ' .
                                       'WHERE id = %d ' .
                                       'AND (created + INTERVAL %d SECOND) > now()',
                                       $state_id, $zone['diff_interval']));
            if ($ret < 0)
                $this->log->err("sensor_handler(): Can't MySQL query\n");

            if (!is_array($ret))
                continue;

            if (isset($ret['id']))
                $active_cnt ++;
        }

        // if not all sensors was triggered
        if ($active_cnt != $total_cnt) {
            $this->log->info("not all sensors is active\n");
            $cmd = './text_spech.php "Уходи" 0';
            if (!$this->hide_sound) {
                run_cmd($cmd);
                player_start(['sounds/access_denyed.wav',
                              'sounds/text.wav'], 100);
            } else
                $this->tg_info('run text spitch cmd: %s', $cmd);

            $msg = sprintf("Срабатал датчик %s из группы \"%s\".\n" .
                "(Поскольку сработал только один датчик из данной группы, то скорее всего это ложное срабатывание)\n",
                $pname, $zone['desc']);
            $this->dg_info($msg);

            run_cmd(sprintf("./image_sender.php current %d", telegram_get_admin_chat_id())); // TODO
            return;
        }

        // ignore ALARM if system already in ALARM state
        $ret = db()->query("SELECT id, zone FROM guard_alarms " .
                           "ORDER BY created DESC LIMIT 1");
        if ($ret < 0)
            $this->log->err("sensor_handler(): Can't MySQL query\n");

        if ($ret && isset($ret['id'])) {
            $alarm_id = $ret['id'];
            $ret = db()->query(sprintf("SELECT id FROM guard_alarms " .
                                       "WHERE id = %d " .
                                       "AND (created + interval %d second) > now() ",
                                       $alarm_id, $zone['alarm_time']));
            if ($ret < 0)
                $this->log->err("sensor_handler(): Can't MySQL query\n");

            if (isset($ret['id'])) {
                $this->log->info("Alarm was ignored because system already in alarm state\n");
                return 0;
            }
        }

        // store guard alarm
        $action_id = db()->insert('guard_alarms',
                                 ['zone' => $zone['name'],
                                  'state_id' => $this->state_id()]);
        if ($action_id < 0)
            $this->log->err("sensor_handler(): Can't insert into guard_alarms\n");

        $this->log->info("Guard set in alarm state!");

        // make snapshots
        $this->make_alarm_photos($action_id);

        // run sirena
        if (!$this->hide_sound)
            player_start('sounds/siren.wav', 100, $zone['alarm_time']);
        else
            $this->tg_info('Run sirena during %d seconds', $zone['alarm_time']);

        $this->tg_alarm("!!! Внимание, Тревога !!!\nСработала зона: '%s', событие: %d\n",
                         $zone['desc'], $action_id);

        // send photos
        $this->send_alarm_photos_to_sr38($action_id);
        $this->send_alarm_photos_to_telegram($action_id);

        // send videos
        $row = db()->query('SELECT UNIX_TIMESTAMP(created) as timestamp ' .
                           'FROM guard_alarms WHERE id = ' . $action_id);
        if ($row < 0)
            $this->log->err("sensor_handler(): Can't MySQL query\n");

        $alarm_timestamp = $row['timestamp'];

        $this->tg_alarm("Загружаю видео файлы по событию %d, ожидайте...", $action_id);

        foreach(conf_guard()['video_cameras'] as $cam) {
            $server_video_urls = $this->upload_cam_video($cam, 'alarm_video',
                                                         $alarm_timestamp - 10, 20,
                                                         $action_id);
            if (!is_array($server_video_urls))
                continue;
            $msg = "";
            foreach($server_video_urls as $url)
                $msg .= sprintf("Видео запись события %d: Камера %d:\n %s\n",
                                $action_id, $cam['id'], $url);

            if ($msg)
                $this->tg_alarm($msg);
        }
        $this->tg_alarm("Процесс загрузки видео по событию %d завершен", $action_id);

        if (!$this->hide_sms)
            modem3g()->send_sms_alarm("Сработала сигнализация! зона %s", $zone['desc']);
    }
}


class Guard_io_handler implements IO_handler {
    function __construct()
    {
        $this->log = new Plog('sr90:Guard_io_handler');
    }

    function name()
    {
        return "guard";
    }

    function trigger_ports() {
        $list = [];
        foreach (guard()->unlocked_zones() as $zone)
            foreach ($zone['io_sensors'] as $pname => $active_state)
                $list[$pname] = $active_state;
        $list['remote_guard_sleep'] = 1;
        $list['remote_guard_ready'] = 1;
        return $list;
    }

    function event_handler($pname, $state)
    {
        switch ($pname) {
        case 'remote_guard_sleep':
            if (guard()->state() == 'sleep')
                return;

            $this->log->info("guard stopped by remote");
            guard()->stop('remote');
            return 0;

        case 'remote_guard_ready':
            if (guard()->state() == 'ready')
                return;

            $this->log->info("guard ready by remote");
            guard()->start('remote');
            return 0;

        default:
            guard()->sensor_handler($pname, $state);
        }
        return 0;
    }
}


function guard()
{
    static $guard = NULL;

    if ($guard)
        return $guard;

    $guard = new Guard;
    return $guard;
}


class Guard_tg_events implements Tg_skynet_events {
    function name()
    {
        return "guard";
    }

    function cmd_list() {
        return [
            ['cmd' => ['включи охрану',
                       'guard on'],
             'method' => 'start'],

            ['cmd' => ['отключи охрану',
                       'выключи охрану',
                       'guard off'],
             'method' => 'stop'],
            ];
    }


    function start($chat_id, $msg_id, $user_id, $arg, $text)
    {
        tn()->send($chat_id, $msg_id, 'ok, попробую');
        $rc = guard()->start('telegram', $user_id);
        switch($rc) {
        case 'already_started':
            tn()->send($chat_id, $msg_id,
                      'Охрана уже включена');
            return;

        case 'db_error':
            tn()->send($chat_id, $msg_id,
                      'Какаято хрень: не вышло поставить на охрану: ошибка базы данных');
            return;

        case 'ok':
            tn()->send($chat_id, $msg_id, 'получилось!');
            return;
        }
    }

    function stop($chat_id, $msg_id, $user_id, $arg, $text)
    {
        tn()->send($chat_id, $msg_id, 'ok, попробую');
        $rc = guard()->stop('telegram', $user_id);
        switch($rc) {
        case 'already_stopped':
            tn()->send($chat_id, $msg_id,
                      'Охрана уже отключена');
            return;

        case 'db_error':
            tn()->send($chat_id, $msg_id,
                      'Какаято хрень: не вышло снять с охраны: ошибка базы данных');
            return;

        case 'ok':
            tn()->send($chat_id, $msg_id, 'получилось!');
            return;
        }
    }
}


class Guard_sms_events implements Sms_events {
    function name()
    {
        return "guard";
    }

    function cmd_list() {
        return [
            ['cmd' => ['start'],
             'method' => 'start'],

            ['cmd' => ['stop'],
             'method' => 'stop'],
            ];
    }

    function start($phone, $user, $arg, $text)
    {
        dump('start');
        tn()->send_to_admin('%s отправил SMS с командой на постановку на охрану',
                    $user['name']);
        $rc = guard()->start('sms', $user['id'], true);
        switch($rc) {
        case 'already_started':
            tn()->send_to_admin('Охрана уже включена');
            return;

        case 'db_error':
            tn()->send_to_admin('Какаято хрень: не вышло поставить на охрану: ошибка базы данных');
            return;

        case 'ok':
            tn()->send_to_admin('получилось!');
            return;
        }
    }

    function stop($phone, $user, $arg, $text)
    {
        tn()->send_to_admin('%s отправил SMS с командой на снятие с охраны',
                   $user['name']);
        $rc = guard()->stop('sms', $user['id'], true);
        switch($rc) {
        case 'already_stopped':
            tn()->send_to_admin('Охрана уже отключена');
            return;

        case 'db_error':
            tn()->send_to_admin('Какаято хрень: не вышло снять с охраны: ошибка базы данных');
            return;

        case 'ok':
            tn()->send_to_admin('получилось!');
            return;
        }
    }
}



class Guard_cron_events implements Cron_events {
    function name()
    {
        return "guard";
    }

    function interval()
    {
        return "min";
    }

    function do()
    {
        // actualize current quard sensor state
        foreach(conf_guard()['zones'] as $zone) {
            foreach($zone['io_sensors'] as $pname => $trig_state) {
                $port_info = port_info($pname);
                $row = db()->query(sprintf("SELECT state FROM io_events " .
                                           "WHERE io_name = '%s' ".
                                               "AND port = %d " .
                                           "ORDER BY id desc LIMIT 1",
                                           $port_info['io_name'], $port_info['pn']));
                $prev_state = $row['state'];
                if ($prev_state == $trig_state)
                    continue;

                if (iop($pname)->state() != $trig_state)
                    continue;

                db()->insert('io_events', ['port_name' => $pname,
                                           'mode' => 'in',
                                           'io_name' => $port_info['io_name'],
                                           'port' => $port_info['pn'],
                                           'state' => $trig_state]);
                printf("Fixed port %s.in.%d\n", $port_info['io_name'], $port_info['pn']);
            }
        }
    }
}

