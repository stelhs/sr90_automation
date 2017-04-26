#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/database.php';
require_once 'config.php';

function main($argv) {
    if (count($argv) < 3) {
        printf("a few scripts parameters\n");
        return -EINVAL;
    }

    $port = $argv[1];
    $port_state = $argv[2];

    if ($port != 6) {
        printf("incorrect input port\n");
        return -EINVAL;
    }

    $db = new Database;
    $rc = $db->connect(conf_db());
    if ($rc) {
        printf("can't connect to database");
        return -EBASE;
    }

    if ($port_state)
        $mode = 'on';
    else
        $mode = 'off';

    $db->insert('ext_power_log', array('state' => $mode));
    $db->close();
    return 0;
}


return main($argv);