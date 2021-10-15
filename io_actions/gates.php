#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'common_lib.php';
require_once 'guard_lib.php';
require_once 'gates_api.php';
require_once 'telegram_api.php';

define("GATES_REMOTE_BUTTON_REVERSE", "/tmp/gates_remote_butt_reverse");

function main($argv)
{
    if (count($argv) < 4) {
        printf("a few scripts parameters\n");
        return -EINVAL;
    }

    $io_name = $argv[1];
    $port = $argv[2];
    $port_state = $argv[3];
    printf("io_name = %s\n", $io_name);
    printf("port = %s\n", $port);
    printf("port_state = %s\n", $port_state);

    if ($io_name != 'sbio1' || $port != 8 || $port_state != 1)
        return;

    $stat = gates_stat();
    $guard_state = get_guard_state();

    if ($guard_state['state'] == 'ready') {
        if ($stat['gates'] == 'not_closed') {
            telegram_send_msg_admin("Ворота закрываются");
            if ($stat['power'] == 'disabled') {
                gates_power_enable();
                sleep(1);
            }
            gates_close_sync();
            gates_power_disable();
            telegram_send_msg_admin("Ворота закрыты");
            @unlink(GATES_REMOTE_BUTTON_REVERSE);
            return;
        }
        return;
    }

    if ($stat['gates'] == 'closed') {
        telegram_send_msg_admin("Ворота открываются");
        @unlink(GATES_REMOTE_BUTTON_REVERSE);
        $rc = gates_open();
        if ($rc)
            telegram_send_msg_admin("Ворота не открылись, видимо нет питания");
        return;
    }

    if (!file_exists(GATES_REMOTE_BUTTON_REVERSE)) {
        telegram_send_msg_admin("Ворота закрываются");
        $rc = gates_close();
        if ($rc)
            telegram_send_msg_admin("Ворота не закрылись, видимо нет питания");
        file_put_contents(GATES_REMOTE_BUTTON_REVERSE, "");
        return;
    }

    telegram_send_msg_admin("Ворота открываются");
    $rc = gates_open();
    if ($rc)
        telegram_send_msg_admin("Ворота не открылись, видимо нет питания");
    @unlink(GATES_REMOTE_BUTTON_REVERSE);
}

exit(main($argv));
