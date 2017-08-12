#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'guard_lib.php';
require_once 'server_control_lib.php';

$utility_name = $argv[0];

function main($argv) {
    if (count($argv) < 4) {
        printf("a few scripts parameters\n");
        return -EINVAL;
    }

    $date = trim($argv[1]);
    $msg = trim($argv[2]);

    return 0;
}


return main($argv);
