<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once 'board_io_api.php';

require_once 'config.php';
require_once 'board_io_api.php';

class Padlock {
    private $log;
    function __construct($name, $desc, $port)
    {
        $this->log = new Plog('sr90:Padlocks');
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
        return iop($this->port)->state();
    }
}

function padlocks_open()
{
    foreach (conf_padlocks() as $padlock) {
        $rc = padlock($padlock['name'])->open();
        if ($rc)
            return sprintf("Can't open padlock '%s': %s",
                            $padlock['desc'], $rc);
    }
}

function padlocks_close()
{
    foreach (conf_padlocks() as $padlock) {
        $rc = padlock($padlock['name'])->close();
        if ($rc)
            return sprintf("Can't close padlock '%s': %s",
                            $padlock['desc'], $rc);
    }
}

function padlocks_stat()
{
    $report = [];
    foreach (conf_padlocks() as $padlock) {
        $padlock['state'] = padlock($padlock['name'])->state();
        $report[] = $padlock;
    }
    return $report;
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
            $rc = padlocks_open();
            if ($rc) {
                tn()->send($chat_id, $msg_id, 'Неполучилось. Причина: %s', $rc);
                return;
            }
            tn()->send($chat_id, $msg_id, 'всё замки открыты');
            return;
        }

        $name = $arg;
        $rc = padlock($name)->open();
        if ($rc) {
            tn()->send($chat_id, $msg_id, 'Неполучилось. Причина: %s', $rc);
            return;
        }
        tn()->send($chat_id, $msg_id, 'открыла');
    }

    function close($chat_id, $msg_id, $user_id, $arg, $text)
    {
        $rc = padlocks_close();
        if ($rc) {
            tn()->send($chat_id, $msg_id, 'Неполучилось. Причина: %s', $rc);
            return;
        }
        tn()->send($chat_id, $msg_id, 'всё замки закрыты');
    }
}


