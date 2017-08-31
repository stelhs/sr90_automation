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
    $cmd = strtolower(trim($argv[4]));

    printf("user: %d, cmd: %s\n", $user_id, $cmd);

    $db = new Database;
    $rc = $db->connect(conf_db());
    if ($rc) {
        msg_log(LOG_ERR, "can't connect to database");
        return -EBASE;
    }

    $telegram = new Telegram_api($db);

    if ($user_id == 0) {
            $telegram->send_message($chat_id,
                "У вас недостаточно прав чтобы выполнить эту операцию\n", $msg_id);
            return 0;
    }
    
    $stat_text = format_global_status_for_telegram(get_global_status($db));
    $telegram->send_message($chat_id, $stat_text, $msg_id);
    
    $ret = file_get_contents('http://sr38.org/plato/?no_view');
    $ret = json_decode($ret, true);
    if ($ret === NULL)
        printf("can't getting images: %s\n", $ret);

    if (is_array($ret)) {
        foreach ($ret as $cam_num => $file) {
            $telegram->send_message($chat_id, sprintf("Камера %d:\n %s\n", 
                                                      $cam_num, $file), $msg_id);
        }
    }
    

    return 0;
}


return main($argv);
