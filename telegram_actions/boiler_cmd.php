#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'boiler_api.php';
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
    case 'start':
        $rc = boiler_start();
        if ($rc) {
            $telegram->send_message($chat_id, 'Не удалось включить котёл', $msg_id);
            return 0;
        }
        $telegram->send_message($chat_id, 'Котёл включен', $msg_id);
        break;

    case 'stop':
        $rc = boiler_stop();
        if ($rc) {
            $telegram->send_message($chat_id, 'Не удалось отключить котёл', $msg_id);
            return 0;
        }
        $telegram->send_message($chat_id, 'Котёл отключен', $msg_id);
        break;

    case 'go':
        $rc = boiler_set_room_t(19);
        if ($rc) {
            $telegram->send_message($chat_id, 'Не удалось задать температуру в помещении', $msg_id);
            return 0;
        }
        $telegram->send_message($chat_id, sprintf('Установлена температура 19 градусов'), $msg_id);
        break;
    }

    return 0;
}

exit(main($argv));
