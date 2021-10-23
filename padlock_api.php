<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';

require_once 'config.php';
require_once 'io_api.php';

class Padlock {
    private $log;
    function __construct($name, $desc, $port)
    {
        $this->log = new Plog('sr90:Padlock');
        $this->name = $name;
        $this->desc = $desc;
        $this->port = $port;
    }

    function open()
    {
        $this->log->info("padlock opening");
        return iop($this->port)->up();
    }

    function close()
    {
        $this->log->info("padlock closing");
        return iop($this->port)->down();
    }

    function state()
    {
        return iop($this->port)->state()[0];
    }
}

class Padlocks {
    function __construct() {
        $this->log = new Plog('sr90:Padlocks');
    }

    function open()
    {
        foreach (conf_padlocks() as $padlock) {
            $rc = padlock($padlock['name'])->open();
            if ($rc[0])
                return sprintf("Can't open padlock '%s': %s",
                                $padlock['desc'], $rc[1]);
        }
    }

    function close()
    {
        foreach (conf_padlocks() as $padlock) {
            $rc = padlock($padlock['name'])->close();
            if ($rc[0])
                return sprintf("Can't close padlock '%s': %s",
                                $padlock['desc'], $rc[1]);
        }
    }

    function stat()
    {
        $report = [];
        foreach (conf_padlocks() as $padlock) {
            $ret = padlock($padlock['name'])->state();
            $padlock['state'] = $ret[0];
            if ($ret[0] < 0)
                $padlock['state'] = 0;
            $report[] = $padlock;
        }
        return $report;
    }

    function stat_text()
    {
        $tg = '';
        $sms = '';
        $stat = $this->stat();
        foreach ($stat as $row) {
            switch ($row['state'][0]) {
            case 0:
                $tg .= sprintf("Замок '%s': закрыт\n", $row['desc']);
                $sms .= sprintf("замок '%s':закр., ", $row['desc']);
                break;

            case 1:
                $tg .= sprintf("Замок '%s': открыт\n", $row['desc']);
                $sms .= sprintf("замок '%s':откр., ", $row['desc']);
                break;
            }
        }
        return [$tg, $sms];
    }
}


function padlock($name)
{
    static $padlocks = [];

    if (isset($padlocks[$name]))
        return $padlocks[$name];

    $found = false;
    foreach (conf_padlocks() as $info) {
        if ($info['name'] == $name) {
            $found = true;
            break;
        }
    }

    if (!$found)
        return NULL; // TODO add logs

    $padlock = new Padlock($info['name'], $info['desc'], $info['port']);
    $padlocks[$name] = $padlock;
    return $padlock;
}

function padlocks()
{
    static $padlocks = NULL;

    if (!$padlocks)
        $padlocks = new Padlocks;

    return $padlocks;
}


class Padlock_tg_events implements Tg_skynet_events {
    function name()
    {
        return "padlocks";
    }

    function cmd_list() {
        return [
            ['cmd' => ['открой РП'],
             'arg' => 'rp',
             'method' => 'open'],

            ['cmd' => ['открой КК'],
             'arg' => 'kk',
             'method' => 'open'],

            ['cmd' => ['открой СК'],
             'arg' => 'sk',
             'method' => 'open'],

            ['cmd' => ['открой мастерскую'],
             'arg' => 'workshop',
             'method' => 'open'],

            ['cmd' => ['открой всё'],
             'method' => 'open'],

            ['cmd' => ['закрой всё'],
             'method' => 'close'],

            ];
    }


    function open($chat_id, $msg_id, $user_id, $arg, $text)
    {
        if (!$arg) {
            $rc = padlocks()->open();
            if ($rc) {
                tn()->send($chat_id, $msg_id, 'Неполучилось. Причина: %s', $rc);
                return;
            }
            tn()->send($chat_id, $msg_id, 'всё замки открыты');
            return;
        }

        $name = $arg;
        $rc = padlock($name)->open();
        if ($rc[0]) {
            tn()->send($chat_id, $msg_id, 'Неполучилось. Причина: %s', $rc[1]);
            return;
        }
        tn()->send($chat_id, $msg_id, 'открыла');
    }

    function close($chat_id, $msg_id, $user_id, $arg, $text)
    {
        $rc = padlocks()->close();
        if ($rc[0]) {
            tn()->send($chat_id, $msg_id, 'Неполучилось. Причина: %s', $rc[1]);
            return;
        }
        tn()->send($chat_id, $msg_id, 'всё замки закрыты');
    }
}


