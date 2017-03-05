#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/database.php';
require_once 'config.php';
require_once 'guard_lib.php';
$utility_name = $argv[0];

function main($argv) {
    $rc = 0;
    if (count($argv) < 4) {
        printf("a few scripts parameters\n");
        return -EINVAL;
    }
        
    $sms_date = trim($argv[1]);
    $phone = trim($argv[2]);
    $sms_text = trim($argv[3]);
    
    $db = new Database;
    $rc = $db->connect(conf_db());
    if ($rc) {
        printf("can't connect to database");
        return -EBASE;
    }
    
    $cmd = parse_sms_command($sms_text);
    
    switch ($cmd['cmd']) {
    case 'off':
        run_cmd("./guard.php state sleep sms");
        break;    

    case 'on':
        dump("./guard.php state ready sms");
        run_cmd("./guard.php state ready sms");
        break;    

    default:
        $undefined_text = 1;
    }    
    
out:    
    $db->close();
    return $rc;
}


return main($argv);