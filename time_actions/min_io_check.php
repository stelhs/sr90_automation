#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'common_lib.php';

function main($argv) {
    foreach(conf_io() as $io_name => $io_data) {
        if ($io_name == 'usio1')
            continue;

        @$content = file_get_contents(sprintf('http://%s:%d/stat',
                                     $io_data['ip_addr'], $io_data['tcp_port']));
        if ($content === FALSE) {
            telegram_send(sprintf("Сбой связи с модулем ввода-вывода %s", $io_name));
            continue;
        }

        $response = json_decode($content, true);
        if ($response === NULL) {
            telegram_send(sprintf("Модуль ввода вывода %s вернула не корректный ответ на запрос: %s",
                                  $io_name, $content));
            continue;
        }

        if ($response['status'] != 'ok') {
            telegram_send(sprintf("При опросе модуля ввода вывода %s выяснилось, что ей плохо: %s",
                                  $io_name, $response['error_msg']));
            continue;
        }

        if (count($response['trigger_log'])) {
            foreach ($response['trigger_log'] as $time => $msg) {
                telegram_send(sprintf("Модуль ввода-вывода %s сообщил, что не смог вовремя передать событие %s. Которое произошло %s",
                                      $io_name, $msg, date("m.d.Y H:i:s", $time)));
            }
            continue;
        }
    }

    return 0;
}

exit(main($argv));

