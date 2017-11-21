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
    $telegram->send_message($chat_id, "поняла, делаю...\n", $msg_id);
    $stat_text = format_global_status_for_telegram(get_global_status());
    $telegram->send_message($chat_id, $stat_text, $msg_id);
    run_cmd(sprintf("./image_sender.php current %d", $chat_id));

    return 0;
}

exit(main($argv));