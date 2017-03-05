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
    echo "Usage: $utility_name <command> <args>\n" . 
             "\tcommands:\n" .
                 "\t\t relay: set relay output state. Args: port_num, 0/1\n" . 
                 "\t\t\texample: $utility_name relay 4 1\n" .
                 "\t\t input: get input state. Args: port_num\n" . 
                 "\t\t\texample: $utility_name input 3\n" .
    
                 "\t\t wdt_on: enable hardware watchdog\n" .
                 "\t\t wdt_off: disable hardware watchdog\n" .
                 "\t\t wdt_reset: reset hardware watchdog\n" .
             "\n\n";
}



function main($argv)
{
    if (!isset($argv[1])) {
        return -EINVAL;
    }

    $cmd = $argv[1];
    
    $db = new Database;
    $rc = $db->connect(conf_db());
    if ($rc) {
        printf("can't connect to database");
        return -EBASE;
    }
    
    $mio = new Mod_io($db);
    
    switch ($cmd) {
    case 'relay':
        if (!isset($argv[2]) || !isset($argv[3])) {
            printf("Invalid arguments: command arguments is not set\n");
            goto err;
        }

        $port = $argv[2];
        $state = $argv[3];

        if ($port < 1 || $port > 7) {
            printf("Invalid arguments: port is not correct. port > 0 and port <= 7\n");
            goto err;
        }

        if ($state < 0 || $state > 1) {
            printf("Invalid arguments: state is not correct. state may be 0 or 1\n");
            goto err;
        }

        $rc = $mio->relay_set_state($port, $state);
        if ($rc < 0) {
            printf("Can't set relay state\n");
        }
        goto out;
        
    case 'input':
        if (!isset($argv[2])) {
            printf("Invalid arguments: command arguments is not set\n");
            goto err;
        }
        
        $port = $argv[2];

        if ($port < 1 || $port > 10) {
            printf("Invalid arguments: port is not correct. port > 0 and port <= 10\n");
            goto err;
        }
        
        $rc = $mio->input_get_state($port);
        if ($rc < 0) {
            printf("Can't get input state\n");
        }
        printf("Input port %d = %d\n", $port, $rc);
        goto out;
        
    case 'wdt_on':
        $rc = $mio->wdt_on();
        if ($rc < 0)
            printf("Can't enable WDT\n");
        goto out;
            
    case 'wdt_off':
        $rc = $mio->wdt_off();
        if ($rc < 0)
            printf("Can't disable WDT\n");
        goto out;
                    
    case 'wdt_reset':
        $rc = $mio->wdt_reset();
        goto out;
    }

out:
    $db->close();  
    return 0;
    
err:
    return -EINVAL;
}



$rc = main($argv);
if ($rc) {
    print_help();
}



?>