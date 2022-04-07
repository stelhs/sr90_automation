<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once 'io_api.php';

require_once 'config.php';

define("NIGHT_LIGHTERS_ENABLED", "/tmp/night_lighters_enabled");


class Lighter {
    private $log;
    function __construct($name, $desc, $port)
    {
        $this->log = new Plog('sr90:Lighter');
        $this->name = $name;
        $this->desc = $desc;
        $this->port = $port;
    }

    function enable() {
        return iop($this->port)->up();
    }

    function disable() {
        return iop($this->port)->down();
    }

    function state() {
        return iop($this->port)->state()[0];
    }
}

class Lighters {
    function __construct() {
        $this->log = new Plog('sr90:Lighters');
    }

    function enable()
    {
        foreach (conf_street_light()['lights'] as $lighter) {
            $rc = lighter($lighter['name'])->enable();
            if ($rc[0] < 0) {
                $err = sprintf("Can't enable lighter '%s': %s",
                               $lighter['desc'], $rc[1]);
                $this->log->err($err);
                return $err;
            }
        }
    }

    function disable()
    {
        foreach (conf_street_light()['lights'] as $lighter) {
            $rc = lighter($lighter['name'])->disable();
            if ($rc[0] < 0) {
                $err = sprintf("Can't disable lighter '%s': %s",
                               $lighter['desc'], $rc[1]);
                $this->log->err($err);
                return $err;
            }
        }
    }

    function stat()
    {
        $report = [];
        foreach (conf_street_light()['lights'] as $lighter) {
            $lighter['state'] = lighter($lighter['name'])->state();
            $report[] = $lighter;
        }
        return $report;
    }

    function is_night()
    {
        $month = date('n');
        $hour = date('H');
        $min = date('i');

        $light_interval = conf_street_light()['light_calendar'][$month];
        list($start_hour, $start_min) = string_to_words($light_interval[0]);
        list($end_hour, $end_min) = string_to_words($light_interval[1]);

        if ($hour == $start_hour and $min > $start_min)
            return true;

        if ($hour > $start_hour)
            return true;

        if ($hour == $end_hour and $min <= $end_min)
            return true;

        if ($hour < $end_hour)
            return true;

        return false;
    }

    function stat_text()
    {
        $tg = '';
        $sms = '';
        $stat = $this->stat();
        foreach ($stat as $row) {
            switch ($row['state']) {
            case 0:
                $tg .= sprintf("Фонарь '%s': отключен\n", $row['desc']);
                $sms .= sprintf("свет '%s':откл, ", $row['desc']);
                break;

            case 1:
                $tg .= sprintf("Фонарь '%s': включен\n", $row['desc']);
                $sms .= sprintf("свет '%s':вкл, ", $row['desc']);
                break;
            }
        }
        return [$tg, $sms];
    }
}

function lighter($name)
{
    static $lighters = [];

    if (isset($lighters[$name]))
        return $lighters[$name];

    $found = false;
    foreach (conf_street_light()['lights'] as $info) {
        if ($info['name'] == $name) {
            $found = true;
            break;
        }
    }

    if (!$found)
        return NULL;

    $lighter = new Lighter($info['name'], $info['desc'], $info['port']);
    $lighters[$name] = $lighter;
    return $lighter;
}

function lighters()
{
    static $lighter = NULL;

    if (!$lighter)
        $lighter = new Lighters;

    return $lighter;
}

class Lighters_tg_events implements Tg_skynet_events {
    function name() {
        return "lighters";
    }

    function cmd_list() {
        return [
            ['cmd' => ['включи свет',
                       'light on'],
             'method' => 'enable'],

            ['cmd' => ['отключи свет',
                       'выключи свет',
                       'light off'],
             'method' => 'disable'],
            ];
    }


    function enable($chat_id, $msg_id, $user_id, $arg, $text)
    {
        $rc = lighters()->enable();
        dump($rc);
        if ($rc) {
            tn()->send($chat_id, $msg_id, 'Неполучилось. Причина: %s', $rc);
            return;
        }
        tn()->send($chat_id, $msg_id, 'включила');
    }

    function disable($chat_id, $msg_id, $user_id, $arg, $text)
    {
        $rc = lighters()->disable();
        if ($rc) {
            tn()->send($chat_id, $msg_id, 'Неполучилось. Причина: %s', $rc);
            return;
        }
        tn()->send($chat_id, $msg_id, 'отключила');
    }
}


class Lighter_sms_events implements Sms_events {
    function name() {
        return "lighters";
    }

    function cmd_list() {
        return [
            ['cmd' => ['light enable'],
             'method' => 'enable'],

            ['cmd' => ['light disable'],
             'method' => 'disable'],
            ];
    }

    function enable($phone, $user, $arg, $text)
    {
        tn()->send_to_admin('%s отправил SMS с командой включить освещение', $user['name']);
        $rc = lighters()->enable();
        if ($rc) {
            tn()->send_to_admin('Неполучилось. Причина: %s', $rc);
            modem3g()->send_sms($phone, 'Неполучилось включить освещение');
            return;
        }
        tn()->send_to_admin('Освещение включено');
        modem3g()->send_sms($phone, 'Освещение включено');
    }

    function disable($phone, $user, $arg, $text)
    {
        tn()->send_to_admin('%s отправил SMS с командой отключить освещение', $user['name']);
        $rc = lighters()->disable();
        if ($rc) {
            tn()->send_to_admin('Неполучилось. Причина: %s', $rc);
            modem3g()->send_sms($phone, 'Неполучилось отключить освещение');
            return;
        }
        tn()->send_to_admin('Освещение отключено');
        modem3g()->send_sms($phone, 'Освещение отключено');
    }
}


class Lighting_cron_events implements Cron_events {
    function name() {
        return "lighting";
    }

    function interval() {
        return "min";
    }

    function __construct() {
        $this->log = new Plog('sr90:Lighting_cron');
    }

    function do()
    {
        $enabled = file_exists(NIGHT_LIGHTERS_ENABLED);
        $is_night = lighters()->is_night();

        if ($is_night and !$enabled) {
            file_put_contents(NIGHT_LIGHTERS_ENABLED, '');
            $rc = lighters()->enable();
            if ($rc) {
                $this->log->err("Can't enable lights: %s\n", $rc);
                return;
            }
            return;
        }

        if (!$is_night and $enabled) {
            unlink_safe(NIGHT_LIGHTERS_ENABLED);
            $rc = lighters()->disable();
            if ($rc) {
                $this->log->err("Can't enable street_light: %s\n", $rc);
                return $rc;
            }
        }
    }
}
