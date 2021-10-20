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


define("PID_DIR", '/tmp/');

define("TEMPERATURES_FILE", "/tmp/temperatures");
define("CURRENT_TEMPERATURES_FILE", "/tmp/current_temperatures");
define("HALT_ALL_SYSTEMS_FILE", "/tmp/halt_all_systems");

$log = new Plog('sr90:common');

interface IO_handler {
    function name();
    function trigger_ports();
    function event_handler($port, $state);
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
            new Ups_batterry_periodically,
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
            new Boards_io_cron_events,
            new Guard_cron_events,
            new Lighting_cron_events,
            new Well_pump_cron_events,
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
            ];
}


class Queue_file {
    function __construct($filename, $size)
    {
        $this->size = $size;
        $this->filename = $filename;
    }

    function put($value)
    {
        @$content = file_get_contents($this->filename);
        if (!$content) {
            file_put_contents($this->filename, json_encode([$value]));
            return;
        }

        @$list = json_decode($content, true);
        if (!is_array($list)) {
            file_put_contents($this->filename, json_encode([$value]));
            return;
        }

        if (count($list) >= $this->size)
            unset($list[0]);

        $list[] = $value;
        $list = array_values($list);
        file_put_contents($this->filename, json_encode($list));
    }

    function get_val()
    {
        @$content = file_get_contents($this->filename);
        if (!$content)
            return NULL;

        @$list = json_decode($content, true);
        if (!is_array($list))
            return NULL;

        sort($list);
        return $list[ceil(count($list) / 2) - 1];
    }
}


function telegram_get_admin_chat_id()
{
    $chat = db()->query("SELECT chat_id FROM telegram_chats " .
                        "WHERE type = 'admin'");
    return $chat['chat_id'];
}

function server_reboot($method, $user_id = NULL)
{
    global $log;
    if ($method == "SMS")
        sms_send('reboot',
                 ['user_id' => $user_id,
                  'groups' => ['sms_observer']],
                 $method);

    $text = sprintf("Сервер ушел на перезагрузку по запросу %s", $method);
    $log->info("server_reboot(): server going to reboot");

    tn()->send_to_admin($text);
    if(DISABLE_HW)
        return;
    run_cmd('halt');
    for(;;);
}

function get_day_night()
{
    $curr_time = time();
    $sun_info = date_sun_info($curr_time, 54.014634, 28.013484);

    if ($curr_time > ($sun_info['nautical_twilight_begin']) &&
        $curr_time < ($sun_info['nautical_twilight_end'] + 3600))
        return 'day';

    return 'night';
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


function skynet_stat()
{
    $ret = run_cmd('uptime');
    preg_match('/up (.+),/U', $ret['log'], $mathes);
    $uptime = $mathes[1];

    return ['guard_stat' => guard()->stat(),
            'balance' => modem3g()->sim_balanse(),
            'modem_stat' => modem3g()->status(),
            'uptime' => $uptime,
            'lighting_stat' => street_lights_stat(),
            'padlocks_stat' => padlocks_stat(),
            'termo_sensors' => termosensors(),
            'battery' => battery_info(),
            'power_state' => power_state(),
            'ups_state' => ups_state(),
            'boiler_state' => boiler()->stat(),
            'gates_state' => gates()->stat(),
    ];
}


function skynet_stat_sms()
{
    $stat = skynet_stat();
    $text = '';
    if (isset($stat['guard_stat'])) {
        $text_who = '';
        switch ($stat['guard_stat']['state']) {
        case 'sleep':
            $mode = "откл.";
            break;

        case 'ready':
            $mode = "вкл.";
            break;
        }
        $text .= sprintf("Охрана:%s, ", $mode);

        if (count($stat['guard_stat']['ignore_zones'])) {
            $text .= sprintf("Игнор: ");
            foreach ($stat['guard_stat']['ignore_zones'] as $zname) {
                $zone = guard()->zone_by_name($zname);
                $text .= sprintf("%s, ", $zone['desc']);
            }
            $text .= '.';
        }

        if (count($stat['guard_stat']['locked_zones'])) {
            $text .= sprintf("Заблокир:");
            foreach ($stat['guard_stat']['locked_zones'] as $zone) {
                $text .= sprintf("%s, ", $zone['desc']);
            }
            $text .= '.';
        }

        if (isset($stat['guard_stat']['user_name']) && $mode)
            $text .= sprintf("%s: %s, ", $mode,
                             $stat['guard_stat']['user_name']);
    }

    if (isset($stat['lighting_stat'])) {
        foreach ($stat['lighting_stat'] as $row) {
            switch ($row['state']) {
            case 0:
                $mode = "откл.";
                break;

            case 1:
                $mode = "вкл.";
                break;
            }
            $text .= sprintf("свет '%s':%s, ", $row['desc'], $mode);
        }
    }
    if (isset($stat['padlocks_stat'])) {
        foreach ($stat['padlocks_stat'] as $row) {
            switch ($row['state']) {
            case 0:
                $mode = "закр.";
                break;

            case 1:
                $mode = "откр.";
                break;
            }
            $text .= sprintf("замок '%s':%s, ", $row['desc'], $mode);
        }
    }

    if (isset($stat['uptime'])) {
        $text .= sprintf("Uptime:%s, ", $stat['uptime']);
    }

    if (isset($stat['balance'])) {
        $text .= sprintf("Баланс:%s, ", $stat['balance']);
    }

    if (isset($stat['battery'])) {
        if (!is_array($stat['battery']))
            $text .= sprintf("ошибка АКБ, ");
        else
            $text .= sprintf("АКБ: %.2fv,%.2fA, ",
                             $stat['battery']['voltage'],
                             $stat['battery']['current']);
    }

    if (isset($stat['power_state'])) {
        $text .= sprintf("Внешн. пит:%d, пит.ИБП:%d, ",
                         $stat['power_state']['input'],
                         $stat['power_state']['ups']);
    }

    if (isset($stat['ups_state'])) {
        $text .= sprintf("250VDC:%d, 14VDC:%d, ups_stat:%s, ",
                         $stat['ups_state']['vdc_out_state'],
                         $stat['ups_state']['standby_state'],
                         $stat['ups_state']['charger_state']);
    }

    if (isset($stat['gates_state'])) {
        $s = $stat['gates_state'];
        $text .= sprintf("ворота %s\n", ($s['gates'] == "closed") ? 'закрыты' : 'открыты');
    }

    return $text;
}


function skynet_stat_telegram()
{
    $stat = skynet_stat();
    $text = '';
    if (isset($stat['guard_stat'])) {
        $text_who = '';
        switch ($stat['guard_stat']['state']) {
        case 'sleep':
            $mode = "отключена";
            $text_who = "Отключил охрану";
            break;

        case 'ready':
            $mode = "включена";
            $text_who = "Включил охрану";
            break;
        }
        $text .= sprintf("Охрана: %s\n", $mode);

        if (count($stat['guard_stat']['ignore_zones'])) {
            $text .= sprintf("Игнорированные зоны:\n");
            foreach ($stat['guard_stat']['ignore_zones'] as $zname) {
                $zone = guard()->zone_by_name($zname);
                $text .= sprintf("               %s\n", $zone['desc']);
            }
        }

        if (count($stat['guard_stat']['locked_zones'])) {
            $text .= sprintf("Заблокированные зоны: ");
            foreach ($stat['guard_stat']['locked_zones'] as $zone)
                $text .= sprintf("               %s\n", $zone['desc']);
        }

        if (isset($stat['guard_stat']['user_name']) && $text_who)
            $text .= sprintf("%s: %s через %s в %s\n", $text_who,
                             $stat['guard_stat']['user_name'],
                             $stat['guard_stat']['method'],
                             $stat['guard_stat']['created']);
    }

    if (isset($stat['lighting_stat'])) {
        foreach ($stat['lighting_stat'] as $row) {
            switch ($row['state']) {
            case 0:
                $mode = "отключено";
                break;

            case 1:
                $mode = "включено";
                break;
            }
            $text .= sprintf("Освещение '%s': %s\n", $row['desc'], $mode);
        }
    }

    if (isset($stat['padlocks_stat'])) {
        foreach ($stat['padlocks_stat'] as $row) {
            switch ($row['state']) {
            case 0:
                $mode = "закрыт";
                break;

            case 1:
                $mode = "открыт";
                break;
            }
            $text .= sprintf("Замок '%s': %s\n", $row['desc'], $mode);
        }
    }

    if (isset($stat['uptime'])) {
        $text .= sprintf("Uptime: %s\n", $stat['uptime']);
    }

    if (isset($stat['balance'])) {
        $text .= sprintf("Баланс счета SIM карты: %s\n", $stat['balance']);
    }

    if (isset($stat['termo_sensors'])) {
        foreach($stat['termo_sensors'] as $sensor)
            $text .= sprintf("Температура %s: %.01f градусов\n", $sensor['name'], $sensor['value']);
    }

    if (isset($stat['battery'])) {
        if (!is_array($stat['battery']))
            $text .= sprintf("ошибка АКБ, ");
        else
            $text .= sprintf("АКБ: %.2fv, %.2fA\n",
                             $stat['battery']['voltage'],
                             $stat['battery']['current']);
    }

    if (isset($stat['power_state'])) {
        $text .= sprintf("Питание на вводе: %s\n" .
                         "Питание на ИБП: %s\n" ,
                         $stat['power_state']['input'] ? 'присутствует' : 'отсутствует',
                         $stat['power_state']['ups'] ? 'присутствует' : 'отсутствует');
    }

    if (isset($stat['ups_state'])) {
        $text .= sprintf("Выходное питание ИБП: %s\n" .
                         "Дежурное питание ИБП: %s\n" .
                         "Состояние ИБП: %s\n",
                         $stat['ups_state']['vdc_out_state'] ? 'присутствует' : 'отсутствует',
                         $stat['ups_state']['standby_state'] ? 'присутствует' : 'отсутствует',
                         $stat['ups_state']['charger_state']);
    }

    if (isset($stat['boiler_state'])) {
        $s = $stat['boiler_state'];
        $text .= sprintf("Состояние котла: %s\n" .
                         "Установленная температура котла: %.1f - %.1f градусов\n" .
                         "Установленная температура в мастерской: %.1f градусов\n" .
                         "Текущая температура в мастерской: %.1f градусов\n" .
                         "Средняя температура в мастерской: %.1f градусов\n" .
                         "Средняя температура в радиаторах: %.1f градусов\n" .
                         "Количество запусков котла за текущие сутки: %d\n" .
                         "Время нагрева за текущие сутки: %s\n" .
                         "Объём потраченного топлива за текущие сутки: %.1f л.\n",
                         $s['state'], $s['target_boiler_t_min'], $s['target_boiler_t_max'],
                         $s['target_room_t'], $s['current_room_t'],
                         $s['overage_room_t'], $s['overage_return_water_t'],
                         $s['ignition_counter'], $s['total_burning_time_text'],
                         $s['total_fuel_consumption']);
    }
    if (isset($stat['gates_state'])) {
        $s = $stat['gates_state'];
        $text .= sprintf("Питание ворот: %s\n" .
                         "Ворота %s\n",
                         ($s['power'] == "enabled") ? 'присутствует' : 'отсутствует',
                         ($s['gates'] == "closed") ? 'закрыты' : 'открыты');
    }
    return $text;
}


function termosensors()
{
    @$content = file_get_contents(CURRENT_TEMPERATURES_FILE);
    if (!$content)
        return [];

    @$rows = json_decode($content, true);
    if (!$rows || !is_array($rows))
        return [];

    $list = [];
    foreach ($rows as $row) {
        if (!isset(conf_termo_sensors()[$row['sensor_name']]))
            continue;
        $list[] = ['name' => conf_termo_sensors()[$row['sensor_name']],
                   'value' => $row['temperature'],
                   'sensor_name' => $row['sensor_name'],
        ];
    }
    return $list;
}

function reboot_sbio($sbio_name)
{
    global $log;
    if (!isset(conf_io()[$sbio_name]))
        return;
    $io_conf = conf_io()[$sbio_name];
    $request = sprintf("http://%s:%d/reboot",
                       $io_conf['ip_addr'],
                       $io_conf['tcp_port']);
    $content = file_get_contents($request);
    if (!$content) {
        $log->err("reboot_sbio(): can't HTTP request %s\n", $request);
        return ['status' => 'error',
                'error_msg' => sprintf('Can`t response from %s', $sbio_name)];
    }

    $ret_data = json_decode($content, true);
    if (!$ret_data) {
        $log->err("reboot_sbio(): can't decode JSON: %s\n", $content);
        return ['status' => 'error',
                'error_msg' => sprintf('Can`t decoded response: %s', $content)];
    }

    if ($ret_data['status'] != 'ok') {
        $log->err("reboot_sbio(): error response %s\n", $content);
        return -1;
    }
    return 0;
}


function halt_all_systems()
{
    global $log;
    $log->info("halt_all_systems()\n", $content);

    if (is_halt_all_systems()) {
        $log->err("halt_all_systems(): is already halted\n");
        return;
    }

    if (DISABLE_HW) {
        perror("FAKE: halt all systems, goodbuy. For undo - remove %s\n",
               HALT_ALL_SYSTEMS_FILE);
        file_put_contents(HALT_ALL_SYSTEMS_FILE, 1);
        return;
    }
    run_cmd("halt");
}

function is_halt_all_systems()
{
    return @file_get_contents(HALT_ALL_SYSTEMS_FILE);
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
        tn()->send($chat_id, $msg_id, 'делаю...');
        $stat_text = skynet_stat_telegram();
        tn()->send($chat_id, $msg_id, $stat_text);
        run_cmd(sprintf("./image_sender.php current %d", $chat_id));
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
        server_reboot('telegram', $user_id);
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
        server_reboot('sms', $user['id']);
    }

    function status($phone, $user, $arg, $text)
    {
        modem3g()->send_sms($phone, skynet_stat_sms());
    }

}


class Temperatures_cron_events implements Cron_events {
    function name() {
        return "temparatures";
    }

    function interval() {
        return "hour";
    }

    function do()
    {
        $temperatures = [];
        foreach(conf_io() as $io_name => $io_data) {
            if ($io_name == 'usio1')
                continue;

            @$content = file_get_contents(sprintf('http://%s:%d/stat',
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
                                        $io_name, $response['error_msg']);
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

        $termo_sensors = termosensors();

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
        $coins = ['ETC', 'BTC', 'BNB'];
        foreach ($coins as $coin) {
            $filename = sprintf(".crypto_currency_%s_max_threshold", strtolower($coin));
            @$threshold = (float)(file_get_contents($filename));
            if (!$threshold)
                continue;

            @$info = json_decode(file_get_contents(
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
            @$threshold = (float)(file_get_contents($filename));
            if (!$threshold)
                continue;

            @$info = json_decode(file_get_contents(
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
        @$content = file_get_contents(TEMPERATURES_FILE);
        if ($content)
            $stat['termo_sensors'] = json_decode($content, 1);

        $stat['io_states'] = io_states();
        $stat['batt_info'] = battery_info();
        $stat['status'] = 'ok';
        return json_encode($stat);
    }
}


