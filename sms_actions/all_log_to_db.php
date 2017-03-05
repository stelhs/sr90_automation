#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/database.php';
require_once '../config.php';
$utility_name = $argv[0];

function main($argv) {
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
    
    $db->insert('incomming_sms', array('phone' => $phone,
                                       'text' => $sms_text,
                                       'received_date' => $sms_date));
    $db->close();
    return 0;
}


return main($argv);