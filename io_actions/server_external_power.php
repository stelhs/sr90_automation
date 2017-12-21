#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'common_lib.php';

function main($argv) {
    if (count($argv) < 4) {
        printf("a few scripts parameters\n");
        return -EINVAL;
    }

    $io_name = $argv[1];
    $port = $argv[2];
    $port_state = $argv[3];

    if ($io_name != 'usio1')
        return -EINVAL;

    if ($port != 5)
        return 0;

    if ($port_state)
        $mode = 'on';
    else
        $mode = 'off';

    db()->insert('ext_power_log', array('state' => $mode));
    return 0;
}

exit(main($argv));