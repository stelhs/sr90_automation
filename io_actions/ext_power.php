#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'common_lib.php';
require_once 'guard_lib.php';
require_once 'telegram_api.php';


function main($argv)
{
    if (count($argv) < 4) {
        printf("a few scripts parameters\n");
        return -EINVAL;
    }

    $io_name = $argv[1];
    $port = $argv[2];
    $port_state = $argv[3];
    printf("io_name = %s\n", $io_name);
    printf("port = %s\n", $port);
    printf("port_state = %s\n", $port_state);

    $external_input_power_port = conf_ups()['external_input_power_port'];
    $external_ups_power_port = conf_ups()['external_ups_power_port'];
    $vdc_out_check_port = conf_ups()['vdc_out_check_port'];
    $standby_check_port = conf_ups()['standby_check_port'];

    if ($io_name == $external_input_power_port['io'] &&
        $port == $external_input_power_port['in_port']) {
        $row = db()->query('SELECT state FROM ext_power_log ' .
                           'WHERE type == "input" ' .
                           'ORDER BY id DESC LIMIT 1');

        $prev_state = $row['state'];
        if ($port_state == $prev_state)
            return 0;

        if ($port_state)
            $msg = 'Питание на вводе восстановлено';
        else
            $msg = 'Питание на вводе отключено';
        telegram_send_msg_admin($msg);

        db()->insert('ext_power_log',
                     ['state' => $port_state,
                      'type' => 'input']);
        return 0;
    }

    if ($io_name == $external_ups_power_port['io'] &&
        $port == $external_ups_power_port['in_port']) {
        $row = db()->query('SELECT state FROM ext_power_log ' .
                           'WHERE type == "ups" ' .
                           'ORDER BY id DESC LIMIT 1');

        $prev_state = $row['state'];
        if ($port_state == $prev_state)
            return 0;

        if ($port_state)
            $msg = 'Питание ИБП восстановлено';
        else
            $msg = 'Питание ИБП отключено';
        telegram_send_msg_admin($msg);

        db()->insert('ext_power_log',
                     ['state' => $port_state,
                      'type' => 'ups']);
        return 0;
    }

    if ($io_name == $vdc_out_check_port['io'] &&
        $port == $vdc_out_check_port['in_port']) {
        if ($port_state)
            return 0;
        $msg = 'Ошибка ИБП: отсутсвует выходное напряжение 250vdc';
        telegram_send_msg_admin($msg);
        return 0;
    }
}

exit(main($argv));
