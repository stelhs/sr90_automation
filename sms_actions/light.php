#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/database.php';
require_once 'config.php';
require_once 'guard_lib.php';
require_once 'modem3g.php';
require_once 'mod_io_lib.php';
require_once 'server_control_lib.php';

$utility_name = $argv[0];

function main($argv) {
    if (count($argv) < 4) {
        printf("a few scripts parameters\n");
        return -EINVAL;
    }

    $db = new Database;
    $rc = $db->connect(conf_db());
    if ($rc) {
        printf("can't connect to database");
        return -EBASE;
    }
    
    $mio = new Mod_io($db);

    $sms_date = trim($argv[1]);
    $user_id = trim($argv[2]);
    $sms_text = trim($argv[3]);

    $args = strings_to_args($sms_text);
    $mode = isset($args[1]) ? $args[1] : NULL;
    if (!$mode)
        return -EINVAL;

    switch ($mode) {
    case "on":
        $mio->relay_set_state(conf_guard()['lamp_io_port'], 1);
        serv_ctrl_send_sms('lighting_on',
                           ['user_id' => $user_id, 
                            'groups' => ['sms_observer']]);
        break;

    case "off":
        $mio->relay_set_state(conf_guard()['lamp_io_port'], 0);
        serv_ctrl_send_sms('lighting_off',
                           ['user_id' => $user_id, 
                            'groups' => ['sms_observer']]);
        break;
        
    default:
        return -EINVAL;
    }

    return 0;
}

return main($argv);