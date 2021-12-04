<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once 'common_lib.php';

class Boiler {
    function __construct() {
        $this->log = new Plog('sr90:Boiler');
    }

    function stat()
    {
        if (DISABLE_HW) {
            $this->log->info("call stat()");
            return ['state' => 'WAITING',
                    'target_boiler_t_min' => 80.0,
                    'target_boiler_t_max' => 85.0,
                    'target_room_t' => 16.0,
                    'current_room_t' => 17.0,
                    'overage_room_t' => 15.0,
                    'overage_return_water_t' => 18.2,
                    'ignition_counter' => 4,
                    'total_burning_time' => 60 * 60,
                    'total_burning_time_text' => '1h',
                    'total_fuel_consumption' => 4.5];
        }

        $http_request = sprintf("http://%s:%d/boiler",
                                conf_boiler()['ip'],
                                conf_boiler()['port']);
        $content = file_get_contents_safe($http_request);
        if (!$content)
            return -1;

        $stat = json_decode($content, true);
        if (!$stat)
            return -1;

        return $stat;
    }


    function reset_stat()
    {
        if (DISABLE_HW) {
            $this->log->info("call reset_stat()");
            return 0;
        }

        $http_request = sprintf("http://%s:%d/boiler/reset_stat",
                                conf_boiler()['ip'],
                                conf_boiler()['port']);
        $content = file_get_contents_safe($http_request);
        if (!$content) {
            $this->log->err("Can't reset stat. http_request = %s", $http_request);
            return -1;
        }

        return 0;
    }


    function start()
    {
        if (DISABLE_HW) {
            $this->log->info("call start()");
            return 0;
        }

        $http_request = sprintf("http://%s:%d/boiler/start",
                                conf_boiler()['ip'],
                                conf_boiler()['port']);
        $content = file_get_contents_safe($http_request);
        if (!$content) {
            $this->log->err("Can't start. http request failed: %s", $http_request);
            return -1;
        }

        $ret = json_decode($content, true);
        if (!$ret) {
            $this->log->err("Can't start. Decode JSON failed: %s", $content);
            return -1;
        }

        if ($ret['status'] != 'ok') {
            $this->log->err("Can't start. Boiler respond error: %s", $content);
            return -1;
        }

        return 0;
    }


    function stop()
    {
        if (DISABLE_HW) {
            $this->log->info("call stop()");
            return 0;
        }

        $http_request = sprintf("http://%s:%d/boiler/stop",
                                conf_boiler()['ip'],
                                conf_boiler()['port']);
        $content = file_get_contents_safe($http_request);
        if (!$content) {
            $this->log->err("Can't stop. http request failed: %s", $http_request);
            return -1;
        }

        $ret = json_decode($content, true);
        if (!$ret) {
            $this->log->err("Can't stop. Decode JSON failed: %s", $content);
            return -1;
        }

        if ($ret['status'] != 'ok') {
            $this->log->err("Can't stop. Boiler respond error: %s", $content);
            return -1;
        }

        return 0;
    }

    function set_room_t($t)
    {
        if (DISABLE_HW) {
            $this->log->info("call set_room_t(%.1f)", $t);
            return 0;
        }

        $http_request = sprintf("http://%s:%d/boiler/setup?target_room_t=%.1f",
                                conf_boiler()['ip'],
                                conf_boiler()['port'],
                                $t);
        $content = file_get_contents_safe($http_request);
        if (!$content) {
            $this->log->err("Can't set_room_t(). http request failed: %s", $http_request);
            return -1;
        }

        $ret = json_decode($content, true);
        if (!$ret) {
            $this->log->err("Can't set_room_t(). Decode JSON failed: %s", $content);
            return -1;
        }

        if ($ret['status'] != 'ok') {
            $this->log->err("Can't set_room_t(). Boiler respond error: %s", $content);
            return -1;
        }

        return 0;
    }

    function month_fuel_consumption()
    {
        $row = db()->query('select sum(fuel_consumption) as total from boiler_statistics ' .
                          'where created > LAST_DAY(NOW() - INTERVAL 1 MONTH) + INTERVAL 1 DAY');
        if ($row == NULL)
            return 0;

        if (!is_array($row) or $row < 0 or !isset($row['total'])) {
            $this->log->err("Can't select boiler fuel consumption");
            return -1;
        }

        return $row['total'] / 1000;
    }

    function year_fuel_consumption()
    {
        $row = db()->query('select sum(fuel_consumption) as total from boiler_statistics ' .
                           'where created > MAKEDATE(year(now()),1)');
        if ($row == NULL)
            return 0;

        if (!is_array($row) or $row < 0 or !isset($row['total'])) {
            $this->log->err("Can't select boiler fuel consumption");
            return -1;
        }

        return $row['total'] / 1000;
    }

    function stat_text()
    {
        $tg = '';
        $sms = '';

        $s = $this->stat();
        $tg .= sprintf("Состояние котла: %s\n" .
                       "Установленная температура котла: %.1f - %.1f градусов\n" .
                       "Установленная температура в мастерской: %.1f градусов\n" .
                       "Текущая температура в мастерской: %.1f градусов\n" .
                       "Средняя температура в мастерской: %.1f градусов\n" .
                       "Средняя температура в радиаторах: %.1f градусов\n" .
                       "Количество запусков котла за текущие сутки: %d\n" .
                       "Время нагрева за текущие сутки: %s\n" .
                       "Объём потраченного топлива за текущие сутки: %.1f л.\n" .
                       "Объём потраченного топлива за текущий месяц: %.1f л.\n" .
                       "Объём потраченного топлива за текущий год: %.1f л.\n",
                         $s['state'], $s['target_boiler_t_min'], $s['target_boiler_t_max'],
                         $s['target_room_t'], $s['current_room_t'],
                         $s['overage_room_t'], $s['overage_return_water_t'],
                         $s['ignition_counter'], $s['total_burning_time_text'],
                         $s['total_fuel_consumption'],
                         $this->month_fuel_consumption(),
                         $this->year_fuel_consumption());

        return [$tg, $sms];
    }

}


function boiler()
{
    static $boiler = NULL;

    if (!$boiler)
        $boiler = new Boiler;

    return $boiler;
}


class Boiler_tg_events implements Tg_skynet_events {
    function name()
    {
        return "boiler";
    }

    function cmd_list() {
        return [
            ['cmd' => ['еду'],
             'method' => 'set_fixed_t'],

            ['cmd' => ['установи температуру'],
             'method' => 'set_t'],

            ['cmd' => ['включи котёл',
                       'запусти котёл'],
             'method' => 'start'],

            ['cmd' => ['отключи котёл',
                       'останови котёл'],
             'method' => 'stop'],

            ];
    }

    function set_fixed_t($chat_id, $msg_id, $user_id, $arg, $text)
    {
        $t = 17;
        $rc = boiler()->set_room_t($t);
        if ($rc) {
            tn()->send($chat_id, $msg_id, 'Не удалось задать температуру в мастерской');
            return 0;
        }
        $stat = boiler()->stat();
        tn()->send($chat_id, $msg_id,
                   "Установлена температура в мастерской %.1f градусов\n" .
                   "Текущая температура в мастерской %.1f градусов\n",
                   $t, $stat['current_room_t']);
    }

    function set_t($chat_id, $msg_id, $user_id, $arg, $text)
    {
        preg_match('/([\d.]+)/i', $text, $m);
        if (!isset($m[1])) {
            tn()->send($chat_id, $msg_id, 'Непоняла какую температуру нужно установить');
            return 0;
        }

        $t = (float)$m[1];
        $rc = boiler()->set_room_t($t);
        if ($rc) {
            tn()->send($chat_id, $msg_id, 'Не удалось задать температуру в мастерской');
            return 0;
        }
        $stat = boiler()->stat();

        tn()->send($chat_id, $msg_id,
                   "Установлена температура в мастерской %.1f градусов\n" .
                   "Текущая температура в мастерской %.1f градусов\n",
                   $t, $stat['current_room_t']);
    }

    function start($chat_id, $msg_id, $user_id, $arg, $text)
    {
        $rc = boiler()->start();
        if ($rc) {
            tn()->send($chat_id, $msg_id, 'Не удалось включить котёл');
            return 0;
        }
        tn()->send($chat_id, $msg_id, 'Котёл включен');
    }

    function stop($chat_id, $msg_id, $user_id, $arg, $text)
    {
        $rc = boiler()->stop();
        if ($rc) {
            tn()->send($chat_id, $msg_id, 'Не удалось отключить котёл');
            return 0;
        }
        tn()->send($chat_id, $msg_id, 'Котёл отключен');
    }
}

class Boiler_cron_events implements Cron_events {
    function __construct() {
        $this->log = new Plog('sr90:Boiler_cron');
    }

    function name() {
        return "boiler";
    }

    function interval() {
        return "day";
    }

    function do()
    {
        $stat = boiler()->stat();

        $row = db()->query('select avg(`temperature`) as t from termo_sensors_log '.
                           'where sensor_name = "28-00000a882264" and ' .
                               'created > (now() - interval 1 day)');
        $outside_t = 0;
        if ($row)
            $this->log->err("Can't get from DB average street temperature\n");

        if (isset($row['t']))
            $outside_t = $row['t'];

        $row_id = db()->insert('boiler_statistics',
                              ['burning_time' => $stat['total_burning_time'],
                               'fuel_consumption' => ($stat['total_fuel_consumption'] * 1000),
                               'ignition_counter' => $stat['ignition_counter'],
                               'return_water_t' => $stat['overage_return_water_t'],
                               'room_t' => $stat['overage_room_t'],
                               'outside_t' => $outside_t]);
        if (!$row_id)
            $this->log->err("Can't insert into boiler_statistics\n");

        boiler()->reset_stat();
        $msg = sprintf("Отчёт по котлу за прошедшие сутки: \n" .
                       "Время горения: %s\n" .
                       "Количество запусков: %d\n" .
                       "Усреднённая температура в мастерской (за сутки): %.1f градусов\n" .
                       "Усреднённая температура в чугунных радиаторах (за сутки): %.1f градусов\n" .
                       "Усреднённая температура на улице (за сутки): %.1f градусов\n" .
                       "Израсходованно дизельного топлива: %.1f литров",
                       $stat['total_burning_time_text'], $stat['ignition_counter'],
                       $stat['overage_room_t'], $stat['overage_return_water_t'],
                       $outside_t, $stat['total_fuel_consumption']);
        if ($stat['ignition_counter'])
            tn()->send_to_admin($msg);
    }
}


