#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'common_lib.php';
require_once 'guard_lib.php';


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

    $external_power_port = conf_ups()['external_input_power_port'];

    if ($io_name == $external_power_port['io'] &&
        $port == $external_power_port['in_port']) {


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

        db()->insert('ext_power_log',
                     ['state' => $port_state,
                      'type' => 'input']);

        telegram_send_admin('ups_system', ['text' => $msg]);
        return 0;
    }

}

exit(main($argv));