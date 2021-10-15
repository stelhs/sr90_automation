#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'gates_api.php';
require_once 'common_lib.php';
require_once 'telegram_api.php';
require_once 'config.php';


function main($argv) {
    global $commands;

    if (count($argv) < 3) {
        perror("a few scripts parameters\n");
        return -EINVAL;
    }

    $user_id = strtolower(trim($argv[1]));
    $chat_id = strtolower(trim($argv[2]));
    $msg_id = strtolower(trim($argv[3]));
    $cmd = strtolower(trim($argv[5]));

    pnotice("user: %d, cmd: %s\n", $user_id, $cmd);

    $telegram = new Telegram_api();

    if ($user_id == 0) {
            $telegram->send_message($chat_id,
                "У вас недостаточно прав чтобы выполнить эту операцию\n", $msg_id);
            return 0;
    }

    switch($cmd) {
    case 'open':
        $rc = gates_open();
        if ($rc) {
            $telegram->send_message($chat_id, 'Не удалось открыть ворота, навреное не отключена охрана', $msg_id);
            return 0;
        }
        gates_close_after(60);
        $telegram->send_message($chat_id, 'Ворота открываются', $msg_id);
        break;

    case 'open-ped':
        $rc = gates_open_ped();
        if ($rc) {
            $telegram->send_message($chat_id, 'Не удалось открыть ворота, навреное не отключена охрана', $msg_id);
            return 0;
        }
        gates_close_after(60);
        $telegram->send_message($chat_id, 'Ворота открываются', $msg_id);
        break;

    case 'close':
        $stat = gates_stat();
        if ($stat['gates'] == 'closed') {
            $telegram->send_message($chat_id, 'Ворота закрыты', $msg_id);
            break;
        }

        $needs_to_power_off = 0;
        if ($stat['power'] == 'disabled') {
            gates_power_enable();
            $needs_to_power_off = 1;
            sleep(1);
        }

        $telegram->send_message($chat_id, 'Ворота закрываются', $msg_id);
        $rc = gates_close_sync();
        if ($rc) {
            $telegram->send_message($chat_id, 'Не удалось закрыть ворота', $msg_id);
            return 0;
        }
        $telegram->send_message($chat_id, sprintf('Ворота успешно закрыты'), $msg_id);
        if ($needs_to_power_off)
            gates_power_disable();
        break;
    }

    return 0;
}

exit(main($argv));
