#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'guard_lib.php';
require_once 'common_lib.php';
require_once 'telegram_api.php';
require_once 'config.php';

function main($argv) {
    $stop_ups_power_port = httpio_port(conf_ups()['stop_ups_power_port']);

    $user_id = strtolower(trim($argv[1]));
    $chat_id = strtolower(trim($argv[2]));
    $msg_id = strtolower(trim($argv[3]));
    $cmd = strtolower(trim($argv[5]));

    $telegram = new Telegram_api();
    if ($user_id == 0) {
        $telegram->send_message($chat_id,
            "У вас недостаточно прав чтобы выполнить эту операцию\n", $msg_id);
        return 0;
    }

    switch ($cmd) {
    case 'start_test':
        $stop_ups_power_port->set(1);
        $msg = "Тестирование ИБП запущенно.";
        $telegram->send_message($chat_id, $msg, $msg_id);
        break;

    case 'stop_test':
        if ($stop_ups_power_port->get() == 0) {
            $telegram->send_message($chat_id, 'Тест не был запущен', $msg_id);
            return;
        }
        $duration = get_last_ups_duration();
        $stop_ups_power_port->set(0);
        $msg = "Тестирование ИБП остановленно. ";
        $msg .= sprintf("Время работы от ИБП составило %d секунд", $duration);
        $telegram->send_message($chat_id, $msg, $msg_id);
        break;
    default:
    }
}

exit(main($argv));