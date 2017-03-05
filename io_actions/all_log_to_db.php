#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/database.php';
require_once '../config.php';
$utility_name = $argv[0];

function main($argv) {
    if (count($argv) < 3) {
        printf("a few scripts parameters\n");
        return -EINVAL;
    }
        
    $port = $argv[1];
    $port_state = $argv[2];
    
    $db = new Database;
    $rc = $db->connect(conf_db());
    if ($rc) {
        printf("can't connect to database");
        return -EBASE;
    }
    
    $db->insert('io_input_actions', array('port' => $port,
                                          'state' => $port_state));
    $db->close();
    return 0;
}


return main($argv);