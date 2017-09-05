#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/database.php';
require_once 'config.php';
require_once 'guard_lib.php';
require_once 'server_control_lib.php';

function main($argv) {
    $rc = 0;
    if (count($argv) < 4) {
        printf("a few scripts parameters\n");
        return -EINVAL;
    }

    $sms_date = trim($argv[1]);
    $user_id = trim($argv[2]);
    $sms_text = trim($argv[3]);

    printf("sms_date = '%s'\n", $sms_date);
    printf("user_id = '%s'\n", $user_id);
    printf("sms_text = '%s'\n", $sms_text);

    $db = new Database;
    $rc = $db->connect(conf_db());
    if ($rc) {
        printf("can't connect to database");
        return -EBASE;
    }

    $mio = new Mod_io($db);
    
    if (!$user_id)
        return -EINVAL;
        
    $user = user_get_by_id($db, $user_id);
    if (!$user || !$user['guard_switch'])
        return -EINVAL;

    $args = strings_to_args($sms_text);
    
    switch ($args[0]) {
    case 'off':
        $cmd = "./guard.php state sleep sms " . $user['id'];
        if (isset($args[1]) && ($args[1] == 'sms'))
            $cmd .= " sms";

        printf("run cmd = '%s'\n", $cmd);
        $ret = run_cmd($cmd);
        if ($ret['rc'] != '0') {
            run_cmd(sprintf("./telegram.php msg_send_all 'Неполучилось отключить охрану через SMS: %s'", 
                            $ret['log']));
            return $ret['rc'];
        }
        break;

    case 'on':
        $cmd = "./guard.php state ready sms " . $user['id'];
        if (isset($args[1]) && $args[1] == 'sms')
            $cmd .= " sms";

        $ret = run_cmd($cmd);
        if ($ret['rc'] != '0') {
            run_cmd(sprintf("./telegram.php msg_send_all 'Неполучилось включить охрану через SMS: %s'", 
                            $ret['log']));
            return $ret['rc'];
        }
        break;

    default:
        return -EINVAL;
    }

    $db->close();
    return $rc;
}


return main($argv);