<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once 'board_io_api.php';

require_once 'config.php';
require_once 'board_io_api.php';

define("DAY_NIGHT_MODE_FILE", "/tmp/day_night_mode");


class Lighter {
    private $log;
    function __construct($name, $desc, $port)
    {
        $this->log = new Plog('sr90:Lighter_class');
        $this->name = $name;
        $this->desc = $desc;
        $this->port = $port;
    }

    function enable()
    {
        return iop($this->port)->up();
    }

    function disable()
    {
        return iop($this->port)->down();
    }

    function state()
    {
        return iop($this->port)->state();
    }
}

function street_lights_enable()
{
    foreach (conf_street_light() as $lighter) {
        $rc = lighter($lighter['name'])->enable();
        if ($rc)
            return sprintf("Can't enable lighter '%s': %s",
                            $lighter['desc'], $rc);
    }
}

function street_lights_disable()
{
    foreach (conf_street_light() as $lighter) {
        $rc = lighter($lighter['name'])->disable();
        if ($rc)
            return sprintf("Can't disable lighter '%s': %s",
                           $lighter['desc'], $rc);
    }
}

function street_lights_stat()
{
    $report = [];
    foreach (conf_street_light() as $lighter) {
        $lighter['state'] = lighter($lighter['name'])->state();
        $report[] = $lighter;
    }
    return $report;
}


function lighter($name)
{
    static $lighters = [];

    if (isset($lighters[$name]))
        return $lighters[$name];

    $found = false;
    foreach (conf_street_light() as $info) {
        if ($info['name'] == $name) {
            $found = true;
            break;
        }
    }

    if (!$found)
        return NULL; // TODO add logs

    $lighter = new Lighter($info['name'], $info['desc'], $info['port']);
    $lighters[$name] = $lighter;
    return $lighter;
}

class Lighters_tg_events implements Tg_skynet_events {
    function name()
    {
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
        $rc = street_lights_enable();
        dump($rc);
        if ($rc) {
            tn()->send($chat_id, $msg_id, 'Неполучилось. Причина: %s', $rc);
            return;
        }
        tn()->send($chat_id, $msg_id, 'включила');
    }

    function disable($chat_id, $msg_id, $user_id, $arg, $text)
    {
        $rc = street_lights_disable();
        if ($rc) {
            tn()->send($chat_id, $msg_id, 'Неполучилось. Причина: %s', $rc);
            return;
        }
        tn()->send($chat_id, $msg_id, 'отключила');
    }
}


class Lighter_sms_events implements Sms_events {
    function name()
    {
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
        $rc = street_lights_enable();
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
        $rc = street_lights_disable();
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
    function __construct()
    {
        $this->log = new Plog('sr90:Lighter_cron');
    }

    function name()
    {
        return "lighting";
    }

    function interval()
    {
        return "min";
    }

    function do()
    {
        @$prev_mode = file_get_contents(DAY_NIGHT_MODE_FILE);
        $curr_mode = get_day_night();
        printf("curr_mode = %s\n", $curr_mode);

        if ($curr_mode == $prev_mode)
            return 0;
        file_put_contents(DAY_NIGHT_MODE_FILE, $curr_mode);

        if ($curr_mode == 'day') {
            $rc = street_lights_disable();
            if ($rc) {
                $this->log->err("Can't disable street_light: %s\n", $rc);
                return;
            }
            return;
        }

        $rc = street_lights_enable();
        if ($rc) {
            $this->log->err("Can't enable street_light: %s\n", $rc);
            return $rc;
        }
    }

}
