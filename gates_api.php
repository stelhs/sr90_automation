<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once 'common_lib.php';
require_once 'board_io_api.php';

define("GATES_CLOSE_AFTER", "/tmp/gates_close_after");
define("GATES_REMOTE_BUTTON_REVERSE", "/tmp/gates_remote_butt_reverse");


class Gates {
    function __construct()
    {
        $this->log = new Plog('sr90:Gates');
    }

    function power_enable()
    {
        iop('gates_power')->up();
        pnotice("gates power enabled but wait 5seconds to starting...");
        sleep(5);
        pnotice("success\n");
        return 0;
    }

    function power_disable()
    {
        if (!$this->is_closed())
            return -EBUSY;
        iop('gates_power')->down();
        $this->close_after_cancel();
        return 0;
    }

    function open()
    {
        if (!$this->is_power_enabled())
            return -EBUSY;
        iop('gates_open')->up();
        sleep(1);
        iop('gates_open')->down();
        $this->close_after_cancel();
        return 0;
    }

    function open_ped()
    {
        if (!$this->is_power_enabled())
            return -EBUSY;
        iop('gates_open_pedestration')->up();
        sleep(1);
        iop('gates_open_pedestration')->down();
        $this->close_after_cancel();
        return 0;
    }

    function close()
    {
        if (!$this->is_power_enabled())
            return -EBUSY;
        iop('gates_close')->up();
        sleep(1);
        iop('gates_close')->down();
        $this->close_after_cancel();
        return 0;
    }

    function close_after($time)
    {
        file_put_contents(GATES_CLOSE_AFTER, time() + $time);
    }

    function close_after_cancel()
    {
        @unlink(GATES_CLOSE_AFTER);
    }

    function close_sync()
    {
        if (!$this->is_power_enabled())
            return -EBUSY;

        $this->close_after_cancel();
        iop('gates_close')->up();
        sleep(1);
        iop('gates_close')->down();
        for($sec = 0; $sec < 100; $sec++) {
            if ($this->is_closed())
                return 0;
            sleep(1);
        }
        return -ECONNFAIL;
    }

    function stat()
    {
        $stat = [];
        if ($this->is_power_enabled())
            $stat['power'] = "enabled";
        else
            $stat['power'] = "disabled";

        if ($this->is_closed())
            $stat['gates'] = "closed";
        else
            $stat['gates'] = "not_closed";
        return $stat;
    }

    function is_closed()
    {
        return iop('gates_closed')->state();
    }

    function is_power_enabled()
    {
        return iop('gates_power')->state();
    }
}

function gates()
{
    static $gates = NULL;
    if (!$gates)
        $gates = new Gates;

    return $gates;
}


class Gates_io_handler implements IO_handler {
    function __construct()
    {
        $this->log = new Plog('sr90:Gates_io_handler');
    }

    function name()
    {
        return "gates";
    }

    function trigger_ports() {
        return ['remote_gates_open_close' => 1,
                'remote_guard_sleep' => 1
        ];
    }

    function event_handler($pname, $state)
    {
        switch ($pname) {
        case 'remote_gates_open_close':
            if (guard()->state() == 'ready') {
                if (!gates()->is_closed()) {
                    tn()->send_to_admin("Ворота закрываются");
                    if (!gates()->is_power_enabled()) {
                        gates()->power_enable();
                        sleep(1);
                    }
                    @unlink(GATES_REMOTE_BUTTON_REVERSE);
                    $this->log->info("gates closing synchronously");
                    $rc = gates()->close_sync();
                    gates()->power_disable();
                    if ($rc) {
                        $this->log->info("Can't close gates");
                        tn()->send_to_admin("Не удалось закрыть ворота");
                        return;
                    }
                    $this->log->info("gates closed");
                    tn()->send_to_admin("Ворота закрыты");
                    return;
                }
                return;
            }

            if (gates()->is_closed()) {
                $this->log->info("gates open for pedestration");
                gates()->open_ped();
                gates()->close_after(30);
                return 0;
            }
            $this->log->info("gates closing");
            gates()->close();
            return 0;

        case 'remote_guard_sleep':
            if (guard()->state() == 'ready')
                return;

            if (gates()->is_closed()) {
                tn()->send_to_admin("Ворота открываются");
                @unlink(GATES_REMOTE_BUTTON_REVERSE);
                $this->log->info("gates opening");
                $rc = gates()->open();
                if ($rc)
                    tn()->send_to_admin("Ворота не открылись, видимо нет питания");
                return;
            }

            if (!file_exists(GATES_REMOTE_BUTTON_REVERSE)) {
                tn()->send_to_admin("Ворота закрываются");
                $this->log->info("gates closing");
                $rc = gates()->close();
                if ($rc)
                    tn()->send_to_admin("Ворота не закрылись, видимо нет питания");
                file_put_contents(GATES_REMOTE_BUTTON_REVERSE, "");
                return;
            }

            tn()->send_to_admin("Ворота открываются");
            $this->log->info("gates opening");
            $rc = gates()->open();
            if ($rc)
                tn()->send_to_admin("Ворота не открылись, видимо нет питания");
            @unlink(GATES_REMOTE_BUTTON_REVERSE);
            return;
        }
    }
}


class Gates_periodically implements Periodically_events {
    function name()
    {
        return "gates";
    }

    function interval()
    {
        return 1;
    }

    function do() {
        if (!file_exists(GATES_CLOSE_AFTER))
            return;

        @$after = (int)file_get_contents(GATES_CLOSE_AFTER);
        if (!$after)
            return;

        if (time() > $after)
            gates()->close();
    }
}


class Gates_tg_events implements Tg_skynet_events {
    function name()
    {
        return "gates";
    }

    function cmd_list() {
        return [
            ['cmd' => ['открой ворота',
                      'gates open'],
             'method' => 'open'],

            ['cmd' => ['открой пешеходу',
                       'открой ворота пешеходу',
                       'gates open ped'],
             'method' => 'open_ped'],

            ['cmd' => ['закрой ворота',
                       'gates close'],
             'method' => 'close'],
            ];
    }

    function open($chat_id, $msg_id, $user_id, $arg, $text)
    {
        $rc = gates()->open();
        if ($rc) {
            tn()->send($chat_id, $msg_id, 'Не удалось открыть ворота, наверное не отключена охрана');
            return;
        }

        gates()->close_after(60);
        tn()->send($chat_id, $msg_id, 'Ворота открываются');
    }

    function open_ped($chat_id, $msg_id, $user_id, $arg, $text)
    {
        $rc = gates()->open_ped();
        if ($rc) {
            tn()->send($chat_id, $msg_id, 'Не удалось открыть ворота, навреное не отключена охрана');
            return;
        }
        gates()->close_after(60);
        tn()->send($chat_id, $msg_id, 'Ворота открываются');
    }

    function close($chat_id, $msg_id, $user_id, $arg, $text)
    {
        if (gates()->is_closed()) {
            tn()->send($chat_id, $msg_id, 'Ворота уже закрыты');
            return;
        }

        $needs_to_power_off = 0;
        if (!gates()->is_power_enabled()) {
            gates()->power_enable();
            $needs_to_power_off = 1;
            sleep(1);
        }

        tn()->send($chat_id, $msg_id, 'Ворота закрываются');
        $rc = gates()->close_sync();
        if ($rc) {
            tn()->send($chat_id, $msg_id, 'Не удалось закрыть ворота');
            return;
        }
        tn()->send($chat_id, $msg_id, 'Ворота успешно закрыты');
        if ($needs_to_power_off)
            gates()->power_disable();
    }
}




