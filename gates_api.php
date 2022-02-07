<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once 'common_lib.php';
require_once 'io_api.php';

define("GATES_REMOTE_BUTTON_REVERSE", "/tmp/gates_remote_butt_reverse");
define("GATES_WAIT_FOR_CLOSED", "/tmp/gates_wait_for_closed");
define("GATES_AUTO_POWER_DISABLE", "/tmp/gates_auto_disable");

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
            return "can`t power disable. gates is not closed";
        iop('gates_power')->down();
        return 0;
    }

    function open()
    {
        if (!$this->is_power_enabled())
            return "can`t close. power is disabled";

        iop('gates_open')->up();
        sleep(1);
        iop('gates_open')->down();
        return 0;
    }

    function open_ped()
    {
        if (!$this->is_power_enabled())
            return "can`t close. power is disabled";

        iop('gates_open_pedestration')->up();
        sleep(1);
        iop('gates_open_pedestration')->down();
        return 0;
    }

    function close($auto_power_disable = false)
    {
        if (!$this->is_power_enabled())
            return "can`t close. power is disabled";

        if ($this->is_closed()) {
            if ($auto_power_disable)
                $this->power_disable();
            return "already closed";
        }

        iop('gates_close')->up();
        sleep(1);
        iop('gates_close')->down();
        file_put_contents(GATES_WAIT_FOR_CLOSED, time() + $this->close_timeout());
        if ($auto_power_disable)
            file_put_contents(GATES_AUTO_POWER_DISABLE, '');
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
        return iop('gates_power')->state()[0];
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
                'open_close' => ['remote_guard_sleep' => 1],
                'gates_closed' => ['gates_closed' => 2],
                ];
    }

    function open_close_ped($pname, $state)
    {
        if (guard()->state() == 'ready') {
            if (!gates()->is_closed()) {
                if (!gates()->is_power_enabled()) {
                    gates()->power_enable();
                    sleep(1);
                }
                unlink_safe(GATES_REMOTE_BUTTON_REVERSE);
                gates()->close(true);
                tn()->send_to_admin("Ворота закрываются");
                return;
            }
            return;
        }

        io()->sequnce_start('guard_lamp',
                               [150, 150]);

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
                (time() - guard()->stoped_timestamp()) < 30) {
            unlink_safe(GATES_REMOTE_BUTTON_REVERSE);
            return;
        }

        io()->sequnce_start('guard_lamp',
                               [150, 150,
                                150, 150]);

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
            if (file_exists(GATES_AUTO_POWER_DISABLE)) {
                gates()->power_disable();
                unlink_safe(GATES_AUTO_POWER_DISABLE);
                tn()->send_to_msg("Ворота закрылись, питание ворот отключено");
                return;
            }
            $this->log->info("gates closed");
            return;
        }
        $this->log->info("gates opening");
   }
}


class Gates_periodically implements Periodically_events {
    function name() {
        return "gates";
    }

    function interval() {
        return 1;
    }


    function do_wait_for_closed()
    {
        if (!file_exists(GATES_WAIT_FOR_CLOSED))
            return;

        $timeout = file_get_contents(GATES_WAIT_FOR_CLOSED);
        if (time() > $timeout) {
            if (gates()->is_closed()) {
                tn()->send_to_admin('Ворота закрылись, но событие от платы ввода-вывода не пришло');
                $msg = 'Ворота закрылись';
                unlink_safe(GATES_WAIT_FOR_CLOSED);
                if (file_exists(GATES_AUTO_POWER_DISABLE)) {
                    gates()->power_disable();
                    unlink_safe(GATES_AUTO_POWER_DISABLE);
                    $msg .= ', питание ворот отключено';
                }
                tn()->send_to_msg($msg);
                return;
            }

            $msg = sprintf("Ворота не закрылись по прошествию %d секунд " .
                    "с момента начала закрытия", gates()->close_timeout());
            unlink_safe(GATES_WAIT_FOR_CLOSED);
            if (file_exists(GATES_AUTO_POWER_DISABLE)) {
                gates()->power_disable();
                unlink_safe(GATES_AUTO_POWER_DISABLE);
                $msg .= ', питание ворот отключено';
            }
            tn()->send_to_msg($msg);
        }
    }

    function do() {
        $this->do_wait_for_closed();
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




