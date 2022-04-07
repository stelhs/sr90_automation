<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once 'common_lib.php';
require_once 'io_api.php';

define("GATES_REMOTE_BUTTON_REVERSE", "/tmp/gates_remote_butt_reverse");
define("GATES_WAIT_FOR_CLOSED", "/tmp/gates_wait_for_closed");

class Gates {
    function __construct()
    {
        $this->log = new Plog('sr90:Gates');
    }

    function power_enable()
    {
        iop('gates_power')->down();
        pnotice("gates power enabled but wait 5seconds to starting...");
        sleep(7);
        pnotice("success\n");
        return 0;
    }

    function power_disable()
    {
        if (!$this->is_closed())
            return "can`t power disable. gates is not closed";
        iop('gates_power')->up();
        return 0;
    }

    function open()
    {
        if (!$this->is_power_enabled())
            return "can`t close. power is disabled";

        iop('gates_close')->down();
        iop('gates_open')->blink(1000, 500, 1);
        return 0;
    }

    function open_ped()
    {
        if (!$this->is_power_enabled())
            return "can`t close. power is disabled";

        iop('gates_open_pedestration')->blink(1000, 500, 1);
        return 0;
    }

    function close()
    {
        if (!$this->is_power_enabled())
            return "can`t close. power is disabled";

        if ($this->is_closed())
            return "already closed";

        iop('gates_open')->down();
        iop('gates_close')->blink(1000, 500, 1);
        file_put_contents(GATES_WAIT_FOR_CLOSED, time() + $this->close_timeout());
        return 0;
    }

    function close_timeout() {
        return 100;
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

    function is_closed() {
        return iop('gates_closed')->state()[0];
    }

    function is_power_enabled() {
        return !iop('gates_power')->state()[0];
    }

    function stat_text()
    {
        $tg = '';
        $sms = '';

        $s = $this->stat();
        $tg .= sprintf("Питание ворот: %s\n" .
                         "Ворота %s\n",
                         ($s['power'] == "enabled") ? 'присутствует' : 'отсутствует',
                         ($s['gates'] == "closed") ? 'закрыты' : 'открыты');
        $sms .= sprintf("ворота %s", ($s['gates'] == "closed") ? 'закрыты' : 'открыты');
        return [$tg, $sms];
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
    function __construct() {
        $this->log = new Plog('sr90:Gates_io_handler');
    }

    function name() {
        return "gates";
    }

    function trigger_ports() {
        return ['open_close_ped' => ['remote_gates_open_close' => 1],
                'open_close' => ['remote_guard_sleep' => 1, 'gates_op_cl_workshop' => 1],
                'gates_closed' => ['gates_closed' => 2],
                ];
    }

    function open_close_ped($pname, $state)
    {
        if (guard()->state() == 'ready') {
            if (!gates()->is_closed()) {
                unlink_safe(GATES_REMOTE_BUTTON_REVERSE);
                gates()->close(true);
                tn()->send_to_admin("Ворота закрываются");
                return;
            }
            return;
        }

        iop('guard_lamp')->blink(200, 200, 1);

        if (gates()->is_closed()) {
            gates()->open_ped();
            $this->log->info("gates open for pedestration");
            return 0;
        }
        gates()->close();
        $this->log->info("gates closing");
    }

    function open_close($pname, $state)
    {
        if (guard()->state() == 'ready' or
                (time() - guard()->stoped_timestamp()) < 60) {
            unlink_safe(GATES_REMOTE_BUTTON_REVERSE);
            return;
        }

        iop('guard_lamp')->blink(200, 200, 2);

        if (gates()->is_closed()) {
            unlink_safe(GATES_REMOTE_BUTTON_REVERSE);
            $rc = gates()->open();
            $this->log->info("gates opening");
            if ($rc)
                tn()->send_to_admin("Ворота не открылись, видимо нет питания");
            return;
        }

        if (!file_exists(GATES_REMOTE_BUTTON_REVERSE)) {
            $rc = gates()->close();
            $this->log->info("gates closing");
            if ($rc)
                tn()->send_to_admin("Ворота не закрылись, причина: %s", $rc);
            file_put_contents(GATES_REMOTE_BUTTON_REVERSE, "");
            return;
        }

        $rc = gates()->open();
        $this->log->info("gates opening");
        if ($rc)
            tn()->send_to_admin("Ворота не открылись, видимо нет питания");
        unlink_safe(GATES_REMOTE_BUTTON_REVERSE);
        return;
    }

    function gates_closed($pname, $state)
    {
        if ($state) {
            unlink_safe(GATES_WAIT_FOR_CLOSED);
            $this->log->info("gates closed");
            tn()->send_to_admin("Ворота закрылись");
            return;
        }
        $this->log->info("gates opening");
   }
}


class Gates_tg_events implements Tg_skynet_events {
    function name() {
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
    }

    function open_ped($chat_id, $msg_id, $user_id, $arg, $text)
    {
        $rc = gates()->open_ped();
        if ($rc) {
            tn()->send($chat_id, $msg_id, 'Не удалось открыть ворота, навреное не отключена охрана');
            return;
        }
    }

    function close($chat_id, $msg_id, $user_id, $arg, $text)
    {
        if (gates()->is_closed()) {
            tn()->send($chat_id, $msg_id, 'Ворота уже закрыты');
            return;
        }

        $needs_to_power_off = false;
        if (!gates()->is_power_enabled()) {
            gates()->power_enable();
            $needs_to_power_off = true;
        }

        tn()->send($chat_id, $msg_id, 'Ворота закрываются');
        gates()->close($needs_to_power_off);
    }
}




