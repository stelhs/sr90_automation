#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'guard_lib.php';
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
    $cmd = strtolower(trim($argv[4]));

    pnotice("user: %d, cmd: %s\n", $user_id, $cmd);

    $telegram = new Telegram_api();

    if ($user_id == 0) {
            $telegram->send_message($chat_id,
                "У вас недостаточно прав чтобы выполнить эту операцию\n", $msg_id);
            return 0;
    }

    switch ($cmd) {
    case 'on':
        $cmd = "./guard.php state ready telegram " . $user_id;
        if (isset($args[5]) && $args[5] == 'sms')
            $cmd .= " sms";

        $telegram->send_message($chat_id, "ok, попробую\n", $msg_id);

        $ret = run_cmd($cmd);
        if ($ret['rc'] != '0')
            $telegram->send_message($chat_id,
            		"Неполучилось. Причина:\n" . $ret['log'], $msg_id);
        break;

    case 'off':
        $cmd = "./guard.php state sleep telegram " . $user_id;
        $lock = isset($args[5]) ? $args[5] : false;

        $telegram->send_message($chat_id, "ok, попробую\n", $msg_id);

        $ret = run_cmd($cmd);
        if ($ret['rc'] != '0')
            $telegram->send_message($chat_id,
            		"Неполучилось. Причина:\n" . $ret['log'], $msg_id);

        if ($lock) {
            // close all padlocks
            $ret = run_cmd('./padlock.php close');
            perror("close all padlocks: %s\n", $ret['log']);
            $telegram->send_message($chat_id, "все замки закрыла\n", $msg_id);
        }
        break;
    }

    return 0;
}

exit(main($argv));