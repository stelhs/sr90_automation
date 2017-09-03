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
    
    $sms_date = trim($argv[1]);
    $user_id = trim($argv[2]);
    $sms_text = trim($argv[3]);
    
    $args = strings_to_args($sms_text);
    $mode = $sms_text;
    if (!$mode)
        return -EINVAL;

    switch ($mode) {
    case "on":
        $ret = run_cmd('./street_light.php enable');
        printf("enable light: %s\n", $ret['log']);

        telegram_send('lighting_on');
        sms_send('lighting_on',
                 ['user_id' => $user_id, 
                  'groups' => ['sms_observer']]);
        break;

    case "off":
        $ret = run_cmd('./street_light.php disable');
        printf("disable light: %s\n", $ret['log']);
        telegram_send('lighting_off');
        sms_send('lighting_off',
                 ['user_id' => $user_id, 
                  'groups' => ['sms_observer']]);
        break;
        
    default:
        return -EINVAL;
    }

    return 0;
}

return main($argv);