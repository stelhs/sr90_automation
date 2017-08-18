#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'guard_lib.php';
require_once 'server_control_lib.php';
require_once 'telegram_api.php';
require_once 'config.php';

$utility_name = $argv[0];

function main($argv) {
    if (count($argv) < 3) {
        printf("a few scripts parameters\n");
        return -EINVAL;
    }

    $user_id = strtolower(trim($argv[1]));
    $chat_id = strtolower(trim($argv[2]));
    $msg = trim($argv[3]);
    $msg_id = isset($argv[4]) ? strtolower(trim($argv[4])) : 0;

    printf("user: %d, msg: %s\n", $user_id, $msg);

    $db = new Database;
    $rc = $db->connect(conf_db());
    if ($rc) {
        msg_log(LOG_ERR, "can't connect to database");
        return -EBASE;
    }

    $telegram = new Telegram_api($db);
    $telegram->send_message($chat_id, 'bla bla', $msg_id);

    return 0;
}


return main($argv);
