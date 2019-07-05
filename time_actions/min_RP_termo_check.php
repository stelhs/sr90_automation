#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'common_lib.php';

function main($argv) {
    $fan_io = 'usio1';
    $fan_port = 7;

    $list = get_termosensors_stat();
    foreach ($list as $row) {
        if ($row['sensor_name'] != "28-00000a5ecf0b")
            continue;

	$fan_state = httpio($fan_io)->relay_get_state($fan_port);

        if ($row['value'] < 33) {
            if ($fan_state)
                httpio($fan_io)->relay_set_state($fan_port, 0);
            continue;
        }

        // if > 33
        if (!$fan_state)
            httpio($fan_io)->relay_set_state($fan_port, 1);
    }
    return 0;
}

exit(main($argv));
