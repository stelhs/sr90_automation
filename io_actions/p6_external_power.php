#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/database.php';
require_once 'config.php';
require_once 'server_control_lib.php';
require_once 'guard_lib.php';

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

    $guard_info = get_guard_state($db);
    if ($guard_info['state'] == 'sleep')
        return 0;

    if ($port_state)
        $mode = 'on';
    else
        $mode = 'off';

    $list_phones = get_users_phones_by_access_type($db, 'sms_observer');
    serv_ctrl_send_sms('external_power', $list_phones, array('mode' => $mode));
    return 0;
}


return main($argv);