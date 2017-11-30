#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'common_lib.php';
require_once 'telegram_api.php';
require_once 'config.php';


function main($argv) {
    global $commands;

    if (count($argv) < 4) {
        perror("a few scripts parameters\n");
        return -EINVAL;
    }

    $user_id = strtolower(trim($argv[1]));
    $chat_id = strtolower(trim($argv[2]));
    $msg_id = strtolower(trim($argv[3]));
    $cmd = strtolower(trim($argv[4]));

    $telegram = new Telegram_api();

    if ($user_id == 0) {
            $telegram->send_message($chat_id,
                "У вас недостаточно прав чтобы выполнить эту операцию\n", $msg_id);
            return 0;
    }

    switch ($cmd) {
    case 'on':
    case 'off':
        $num = isset($argv[5]) ? strtolower(trim($argv[5])) : "";
        $mode = $cmd == 'on' ? 'open' : 'close';
        $ret = run_cmd(sprintf("./padlock.php %s %d", $mode, $num));
        dump($ret);
        if (!$ret || $ret['rc']) {
            $telegram->send_message($chat_id,
            		"Неполучилось. Причина:\n" . $ret['log'], $msg_id);
            return 0;
        }
        $mode = $cmd == 'on' ? 'открыт' : 'закрыт';
        $telegram->send_message($chat_id, sprintf("Замок %s %s\n", $num, $mode), $msg_id);
        return 0;

    default:
        return -EINVAL;
    }

    return 0;
}

exit(main($argv));