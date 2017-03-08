#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/database.php';
require_once 'config.php';
require_once 'sequencer_lib.php';

$utility_name = $argv[0];

function main($argv) {
    if (count($argv) < 3) {
        printf("a few scripts parameters\n");
        return 1;
    }
        
    $port = $argv[1];
    $port_state = $argv[2];
    
    $db = new Database;
    $rc = $db->connect(conf_db());
    if ($rc) {
        printf("can't connect to database");
        return 1;
    }
    
    if ($port_state)
        $state = 'day';
    else
        $state = 'night';
        
    if ($state == 'day')
        sequncer_stop(conf_guard()['lamp_io_port']);
    
    $db->insert('day_night', array('state' => $state));
    return 0;
}


return main($argv);