#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'config.php';
require_once 'mod_io_lib.php';
$utility_name = $argv[0];

function print_help()
{
    global $utility_name;
    echo "Usage: $utility_name <port_num> <enable_time> <disable_time> ...\n" . 
             "\tStarting port state changer sequencer\n" .
             "\t\tExample:\n" .
             "\t\t\t $utility_name 4 500 500 300 500\n" .
    	"\n\n";
}


function main($argv)
{
    $rc = 0;
    if (!isset($argv[1])) {
        return -1;
    }
    
    $port = $argv[1];
    if ($port < 1 || $port > 7) {
        printf("Invalid arguments: port is not correct. port > 0 and port <= 7\n");
        goto out;
    }

    $sequence = $argv;
    unset($sequence[0]);
    unset($sequence[1]);

    $db = new Database;
    $rc = $db->connect(conf_db());
    if ($rc) {
        printf("can't connect to database");
        return 1;
    }
    
    $mio = new Mod_io($db);    

    if (count($sequence) == 1 && $sequence[2] == 0) {
        $mio->relay_set_state($port, 0);
        goto out;
    }
    
    $mode = true;
    foreach ($sequence as $time) {
        $mio->relay_set_state($port, $mode);
        $mode = !$mode;
        usleep($time * 1000);
    }

out:
    $db->close();  
    return $rc;
}

$rc = main($argv);
if ($rc) {
    print_help();
}

