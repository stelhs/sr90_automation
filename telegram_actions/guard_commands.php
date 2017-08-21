#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'guard_lib.php';
require_once 'server_control_lib.php';
require_once 'telegram_api.php';
require_once 'config.php';


function main($argv) {
    global $commands;
    
    if (count($argv) < 3) {
        printf("a few scripts parameters\n");
        return -EINVAL;
    }

    $user_id = strtolower(trim($argv[1]));
    $chat_id = strtolower(trim($argv[2]));
    $msg_id = strtolower(trim($argv[3]));
    $cmd = trim($argv[4]);

    printf("user: %d, cmd: %s\n", $user_id, $cmd);

    $db = new Database;
    $rc = $db->connect(conf_db());
    if ($rc) {
        msg_log(LOG_ERR, "can't connect to database");
        return -EBASE;
    }

    $telegram = new Telegram_api($db);
    
    switch ($cmd) {
    case 'on':
        $cmd = "./guard.php state ready telegram " . $user_id;
        if (isset($args[5]) && $args[5] == 'sms')
            $cmd .= " sms";

        $ret = run_cmd($cmd);
        if ($ret['rc'])
            $telegram->send_message($chat_id, "Охрана включена\n", $msg_id);
        else
            $telegram->send_message($chat_id,
            		"Неполучилось. Причина:\n" . $ret['log'], $msg_id);
        break;

    case 'off':
        $cmd = "./guard.php state sleep telegram " . $user_id;
        if (isset($args[5]) && $args[5] == 'sms')
            $cmd .= " sms";

        $ret = run_cmd($cmd);
        if ($ret['rc'])
            $telegram->send_message($chat_id, "Охрана отключена\n", $msg_id);
        else
            $telegram->send_message($chat_id,
            		"Неполучилось. Причина:\n" . $ret['log'], $msg_id);
        break;
    }
    
    $telegram->send_message($chat_id, $msg, $msg_id);

    return 0;
}


return main($argv);
