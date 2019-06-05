#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'common_lib.php';
require_once 'telegram_api.php';

function main($argv) {
    $temperatures = [];
    foreach(conf_io() as $io_name => $io_data) {
        if ($io_name == 'usio1')
            continue;

        @$content = file_get_contents(sprintf('http://%s:%d/stat',
                                     $io_data['ip_addr'], $io_data['tcp_port']));
        if ($content === FALSE) {
            telegram_send_msg_admin(sprintf("Сбой связи с модулем ввода-вывода %s", $io_name));
            run_cmd(sprintf("./init_io_actions.php %s", $io_name));
            continue;
        }

        $response = json_decode($content, true);
        if ($response === NULL) {
            telegram_send_msg_admin(sprintf("Модуль ввода вывода %s вернул не корректный ответ на запрос: %s",
                                    $io_name, $content));
            continue;
        }

        if ($response['status'] != 'ok') {
            telegram_send_msg_admin(sprintf("При опросе модуля ввода-вывода %s, он вернул ошибку: %s",
                                    $io_name, $response['error_msg']));
            continue;
        }

        if ($response['uptime'] == '0 min' || $response['uptime'] == '1 min')
            telegram_send_msg_admin(sprintf("Модуль ввода-вывода %s недавно перезагрузился", $io_name));

        if (isset($response['termo_sensors'])) {
            $sensors = $response['termo_sensors'];
            foreach ($sensors as $sensor) {
                $row = ['io_name' => $io_name,
                           'sensor_name' => $sensor['name'],
                           'temperature' => $sensor['temperature']];
                db()->insert('termo_sensors_log', $sensor);
                $temperatures[] = $row;
            }
        }

        if (count($response['trigger_log'])) {
            foreach ($response['trigger_log'] as $time => $msg) {
                telegram_send_msg_admin(sprintf("Модуль ввода-вывода %s сообщил, " .
                                                "что не смог вовремя передать событие %s. " .
                                                "Которое произошло %s",
                                                 $io_name, $msg, date("m.d.Y H:i:s", $time)));
            }
        }
    }
    file_put_contents(CURRENT_TEMPERATURES_FILE, json_encode($temperatures));

    return 0;
}

exit(main($argv));

