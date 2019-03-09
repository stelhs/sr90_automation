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

    run_cmd(sprintf("./text_spech.php '%s'", $cmd));
    $telegram->send_message($chat_id, sprintf("По громкоговорителю озвучивается сообщение: '%s'.\n" .
                                              "Ожидайте пару минут видео-звукозапись сообщения и реакции окружающих.", $cmd), $msg_id);


    run_cmd(sprintf("./video_sender.php by_timestamp %d 15 1,2 %d", time(), $chat_id));
    return 0;
}

exit(main($argv));
