<?php

require_once '/usr/local/lib/php/database.php';
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once 'config.php';
require_once 'modem3g.php';
require_once 'telegram_lib.php';
require_once 'boiler_api.php';
require_once 'gates_api.php';
require_once 'guard_api.php';
require_once 'power_api.php';
require_once 'dvr_api.php';


define("PID_DIR", '/tmp/');

define("FAKE_HALT_ALL_SYSTEMS_FILE", "/tmp/halt_all_systems");
define("TEMPERATURES_FILE", "/tmp/temperatures");


$log = new Plog('sr90:common');

interface IO_handler {
    function name();
    function trigger_ports();
}

interface Periodically_events {
    function name();
    function do();
}

interface Cron_events {
    function name();
    function interval();
    function do();
}

interface Tg_skynet_events {
    function name();
    function cmd_list();
}

interface Sms_events {
    function name();
    function cmd_list();
}

interface Http_handler {
    function name();
    function requests();
}


function periodically_list()
{
    return [new Gates_periodically,
            new Ups_periodically,
            new Telegram_periodically,
            new Modem3g_periodically,
            ];
}

function cron_handlers()
{
    return [new Boiler_cron_events,
            new Temperatures_cron_events,
            new Cryptocurrancy_cron_events,
            new Boards_io_min_cron_events,
            new Boards_io_day_cron_events,
            new Guard_cron_events,
            new Lighting_cron_events,
            new Well_pump_cron_events,
            new Check_sys_cron_events,
            new Dvr_cron_events,
            ];
}

function io_handlers()
{
    return [new Guard_io_handler,
            new Well_pump_io_handler,
            new Ext_power_io_handler,
            new Gates_io_handler,
            ];
}

function telegram_handlers()
{
    return [new Guard_tg_events,
            new Gates_tg_events,
            new Padlock_tg_events,
            new Lighters_tg_events,
            new Boiler_tg_events,
            new Modem_tg_events,
            new Common_tg_events,
            new Ups_tg_events
            ];
}

function http_handlers()
{
    return [new Http_io_handler,
            new Stat_io_handler,
            new Dbg_io_handler,
            new Dvr_handler,
            ];
}

function sms_handlers()
{
    return [new Guard_sms_events,
            new Inet_sms_events,
            new Lighter_sms_events,
            new Common_sms_events,
            ];
}


function tg_admin_chat_id() //TODO
{
    $chat = db()->query("SELECT chat_id FROM telegram_chats " .
                        "WHERE type = 'admin'");
    return $chat['chat_id'];
}



function user_get_by_phone($phone)
{
    global $log;
    $user = db()->query("SELECT * FROM users " .
                        "WHERE phones LIKE \"%%%s%%\" AND enabled = 1", $phone);

    if (!$user) {
        $log->err("user_get_by_phone(): can't Mysql query\n");
        return NULL;
    }

    $user['phones'] = string_to_array($user['phones']);
    return $user;
}

function user_by_id($user_id)
{
    global $log;
    if (!$user_id)
        return NULL;

    $user = db()->query("SELECT * FROM users " .
                        "WHERE id = %d AND enabled = 1", $user_id);

    if (!$user) {
        $log->err("user_by_id(): can't Mysql query\n");
        return NULL;
    }

    $user['phones'] = string_to_array($user['phones']);
    return $user;
}

function user_get_by_telegram_id($telegram_user_id)
{
    global $log;
    $user = db()->query("SELECT * FROM users " .
                        "WHERE telegram_id = %d AND enabled = 1",
                        $telegram_user_id);

    if (!$user) {
        $log->err("user_get_by_telegram_id(): can't Mysql query\n");
        return NULL;
    }

    $user['phones'] = string_to_array($user['phones']);
    return $user;
}


function users_for_alarm()
{
    global $log;
    $users = db()->query_list('SELECT * FROM users '.
                              'WHERE guard_alarm = 1 AND enabled = 1');
    if (!$users) {
        $log->err("users_for_alarm(): can't Mysql query\n");
        return NULL;
    }

    $list_phones = array();
    foreach ($users as $user)
        $users['phones'] = string_to_array($user['phones']);

    return $users;
}


function skynet_stat_sms()
{
    $list = ['Охрана' => guard(),
             'Освещение' => lighters(),
             'Замки' => padlocks(),
             'Модем вспомагательный' => modem3g(),
             'Платы I/O' => io(),
             'Питание' => power(),
             'Дизельный котёл' => boiler(),
             'Ворота' => gates(),
             'Видеокамеры' => dvr(),
            ];

    $text = '';
    $sep = '';
    foreach ($list as $name => $system) {
        $ret = $system->stat_text();
        if ($ret[1])
            $text .= sprintf("%s%s", $sep, $ret[1]);
        $sep = ', ';
    }
    return $text;
}


function skynet_stat_telegram()
{
    $list = ['Охрана' => guard(),
             'Освещение' => lighters(),
             'Замки' => padlocks(),
             'Модем вспомагательный' => modem3g(),
             'Платы I/O' => io(),
             'Питание' => power(),
             'Дизельный котёл' => boiler(),
             'Ворота' => gates(),
             'Видеокамеры' => dvr(),
            ];

    $text = '';
    foreach ($list as $name => $system) {
        $text .= sprintf("\n%s:\n",$name);
        $ret = $system->stat_text();
        if ($ret[0])
            $text .= $ret[0];
    }
    return $text;
}


function file_get_contents_safe(
        $filename,
        $use_include_path = false,
        $context = NULL) {
    $h = function ($errno, $str, $file, $line) use($filename) {
        $text = sprintf("Error: %s\n", $str);
        plog(LOG_ERR, sprintf('sr90:common'), $text);
    };

    set_error_handler($h);
    $c = file_get_contents($filename, $use_include_path, $context);
    restore_error_handler();
    return $c;
}


function errno_to_str($errno)
{
    $level = '';
    switch($errno) {
    case E_COMPILE_ERROR:
    case E_RECOVERABLE_ERROR:
    case E_USER_ERROR:
    case E_COMPILE_ERROR:
    case E_CORE_ERROR:
    case E_ERROR: $level = "Error"; break;
    case E_USER_WARNING:
    case E_COMPILE_WARNING:
    case E_CORE_WARNING:
    case E_WARNING: $level = "Warning"; break;
    case E_USER_NOTICE:
    case E_NOTICE: $level = "Notice"; break;
    case E_PARSE: $level = "Parse error"; break;
    case E_STRICT: $level = "Strict error"; break;
    case E_DEPRECATED: $level = "Deprecated"; break;
    }
    return $level;
}


class Common_tg_events implements Tg_skynet_events {
    function name() {
        return "common";
    }

    function cmd_list() {
        return [
            ['cmd' => ['статус'],
             'method' => 'status'],

            ['cmd' => ['скажи'],
             'method' => 'tell'],

            ['cmd' => ['перезагрузись'],
             'method' => 'reboot'],
            ];
    }

    function status($chat_id, $msg_id, $user_id, $arg, $text)
    {
        $event_time = time();
        tn()->send($chat_id, $msg_id, 'делаю...');
        $stat_text = skynet_stat_telegram();
        $stat_text .= sprintf("%s?time_position=%s\n",
                            conf_dvr()['site'], $event_time);
        tn()->send($chat_id, $msg_id, $stat_text);
    }

    function tell($chat_id, $msg_id, $user_id, $arg, $text)
    {
        $cmd = sprintf("./text_spech.php '%s'", $text);
        run_cmd($cmd);
        tn()->send($chat_id, $msg_id,
            "По громкоговорителю озвучивается сообщение: '%s'.\n" .
            "Ожидайте пару минут видео-звукозапись сообщения и реакции окружающих.", $text);

        $cmd = sprintf("./video_sender.php by_timestamp %d 15 1,2 %d", time(), $chat_id);
        dump($cmd);
        $ret = run_cmd($cmd);
        if ($ret['rc'])
            tn()->send_to_admin("Не удалось захватить видео: %s", $ret['log']);
    }

    function reboot($chat_id, $msg_id, $user_id, $arg, $text)
    {
        tn()->send($chat_id, $msg_id, 'Выполняю перезагрузку');
        power()->server_reboot('telegram', $user_id);
    }
}


class Common_sms_events implements Sms_events {
    function name() {
        return "common";
    }

    function cmd_list() {
        return [
             ['cmd' => ['help'],
             'method' => 'help'],

            ['cmd' => ['reboot'],
             'method' => 'reboot'],

            ['cmd' => ['stat'],
             'method' => 'status'],
            ];
    }

    function help($phone, $user, $arg, $text)
    {
        $info = 'Команды: ';
        $sep = '';
        foreach (sms_handlers() as $handler)
            foreach ($handler->cmd_list() as $row)
                foreach ($row['cmd'] as $cmd) {
                    $info .= sprintf("%s%s", $sep, $cmd);
                    $sep = '|';
                }
        modem3g()->send_sms($phone, $info);
    }

    function reboot($phone, $user, $arg, $text)
    {
        tn()->send_to_admin('Выполняю перезагрузку');
        modem3g()->send_sms($phone, 'Сервер ушел в перезагрузку');
        power()->server_reboot('sms', $user['id']);
    }

    function status($phone, $user, $arg, $text)
    {
        modem3g()->send_sms($phone, skynet_stat_sms());
    }
}


class Temperatures_cron_events implements Cron_events {
    function name() {
        return "temperatures";
    }

    function interval() {
        return "hour";
    }

    function do()
    {
        if (DISABLE_HW)
            return;

        $temperatures = [];
        foreach(conf_io() as $io_name => $io_data) {
            if ($io_name == 'usio1')
                continue;

            @$content = file_get_contents_safe(sprintf('http://%s:%d/stat',
                                         $io_data['ip_addr'], $io_data['tcp_port']));
            if ($content === FALSE) {
                tn()->send_to_admin("Сбой связи с модулем %s", $io_name);
                trig_io_board_for_curr_state($io_name);
                continue;
            }

            $response = json_decode($content, true);
            if ($response === NULL) {
                tn()->send_to_admin("Модуль ввода вывода %s вернул не корректный ответ на запрос: %s",
                                        $io_name, $content);
                continue;
            }

            if ($response['status'] != 'ok') {
                tn()->send_to_admin("При опросе модуля ввода-вывода %s, он вернул ошибку: %s",
                                        $io_name, $response['reason']);
                continue;
            }

            if (!isset($response['termo_sensors']))
                continue;

            $sensors = $response['termo_sensors'];
            foreach ($sensors as $sensor) {
                    if ($sensor['temperature'] < -60 || $sensor['temperature'] > 100)
                        continue;
                    $row = ['io_name' => $io_name,
                            'sensor_name' => $sensor['name'],
                            'temperature' => $sensor['temperature']];
                    db()->insert('termo_sensors_log', $row);
                    $temperatures[] = $row;
            }
        }

        file_put_contents(CURRENT_TEMPERATURES_FILE, json_encode($temperatures));


        pnotice("remove old temperatures data\n");
        db()->query('delete from termo_sensors_log where ' .
                    'created < (now() - interval 12 month)');

        $termo_sensors = io()->termosensors();

        $temperature_stat = [];
        foreach ($termo_sensors as $sensor) {
            $temperature_stat[$sensor['sensor_name']] = $sensor;

            pnotice("calculate minimum for %s\n", $sensor['sensor_name']);
            $query = sprintf("SELECT created, temperature " .
                "FROM `termo_sensors_log` " .
                "WHERE sensor_name = '%s' " .
                "AND created > (now() - INTERVAL 1 DAY) " .
                "ORDER BY temperature ASC LIMIT 1",
                $sensor['sensor_name']);
            $row = db()->query($query);
            $temperature_stat[$sensor['sensor_name']]['min'] = $row;

            pnotice("calculate maximum for %s\n", $sensor['sensor_name']);
            $query = sprintf("SELECT created, temperature " .
                "FROM `termo_sensors_log` " .
                "WHERE sensor_name = '%s' " .
                "AND created > (now() - INTERVAL 1 DAY) " .
                "ORDER BY temperature DESC LIMIT 1",
                $sensor['sensor_name']);
            $row = db()->query($query);
            $temperature_stat[$sensor['sensor_name']]['max'] = $row;

            pnotice("calculate average for %s\n", $sensor['sensor_name']);
            $query = sprintf("SELECT avg(temperature) as temperature " .
                "FROM `termo_sensors_log` " .
                "WHERE sensor_name = '%s' " .
                "AND created > (now() - INTERVAL 1 DAY)",
                $sensor['sensor_name']);
            $row = db()->query($query);
            $temperature_stat[$sensor['sensor_name']]['avg'] = $row['temperature'];
        }

        file_put_contents(TEMPERATURES_FILE, json_encode($temperature_stat));
    }
}


class Cryptocurrancy_cron_events implements Cron_events {
    function name() {
        return "cryptocurrancy";
    }

    function interval() {
        return "min";
    }

    function do()
    {
        $coins = ['ETC', 'ADA'];
        foreach ($coins as $coin) {
            $filename = sprintf(".crypto_currency_%s_max_threshold", strtolower($coin));
            if (!file_exists($filename))
                continue;

            $threshold = (float)(file_get_contents($filename));
            if (!$threshold)
                continue;

            $info = json_decode(file_get_contents_safe(
                                 sprintf("https://api.binance.com/api/v3/ticker/price?symbol=%sUSDT", $coin)), true);
            if (!is_array($info))
                continue;

            if ($info['price'] < $threshold)
                continue;

            $msg = sprintf("Цена на %s %f USDT", $coin, $info['price']);
            tn()->send_to_admin($msg);
            file_put_contents($filename, "");
        }


        foreach ($coins as $coin) {
            $filename = sprintf(".crypto_currency_%s_min_threshold", strtolower($coin));
            if (!file_exists($filename))
                continue;

            $threshold = (float)(file_get_contents($filename));
            if (!$threshold)
                continue;

            $info = json_decode(file_get_contents_safe(
                                 sprintf("https://api.binance.com/api/v3/ticker/price?symbol=%sUSDT", $coin)), true);
            if (!is_array($info))
                continue;

            if ($info['price'] > $threshold)
                continue;

            $msg = sprintf("Цена на %s %f USDT", $coin, $info['price']);
            tn()->send_to_admin($msg);
            file_put_contents($filename, "");
        }
    }
}


class Check_sys_cron_events implements Cron_events {
    function name() {
        return "common";
    }

    function interval() {
        return "hour";
    }

    function do()
    {
        $msg = '';
        if (DISABLE_HW)
            $msg .= sprintf("DISABLE_HW\n");

        if(file_exists('GUARD_TESTING'))
            $msg .= sprintf("GUARD_TESTING\n");

        if(file_exists('HIDE_TELEGRAM'))
            $msg .= sprintf("HIDE_TELEGRAM\n");
        tn()->send_to_admin($msg);
    }
}


class Stat_io_handler implements Http_handler {
    function name() {
        return "stat";
    }

    function requests() {
        return ['/stat' => ['method' => 'GET',
                            'handler' => 'stat',
                            ]];
    }

    function __construct() {
        $this->log = new Plog('sr90:Stat_io_handler');
    }

    function stat($args, $from, $request)
    {
        $stat = [];
        if (file_exists(TEMPERATURES_FILE)) {
            $content = file_get_contents(TEMPERATURES_FILE);
            if ($content)
                $stat['termo_sensors'] = json_decode($content, 1);
        }

        $stat['io_states'] = io()->stored_states();
        $stat['batt_info'] = power()->battery_info();
        $stat['status'] = 'ok';
        return json_encode($stat);
    }
}


class Dbg_io_handler implements Http_handler {
    function name() {
        return "dbg";
    }

    function requests() {
        return ['/dbg' => ['method' => 'GET',
                           'handler' => 'dbg',
                            ]];
    }

    function __construct() {
        $this->log = new Plog('sr90:Test_io_handler');
    }

    function dbg($args, $from, $request)
    {
        return json_encode(['status' => 'ok']);
    }
}


