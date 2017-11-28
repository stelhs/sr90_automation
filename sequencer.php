#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'config.php';
require_once 'httpio_lib.php';
$utility_name = $argv[0];

function print_help()
{
    global $utility_name;
    echo "Usage: $utility_name <io_name> <port_num> <enable_time> <disable_time> ...\n" .
             "\tStarting port state changer sequencer\n" .
             "\t\tExample:\n" .
             "\t\t\t $utility_name usio1 4 500 500 300 500\n" .
    	"\n\n";
}


function main($argv)
{
    if (!isset($argv[1]))
        return -1;

    $io_name = $argv[1];

    $port = $argv[2];
    if ($port < 1 || $port > 7) {
        perror("Invalid arguments: port is not correct. port > 0 and port <= 7\n");
        return 0;
    }

    $sequence = $argv;
    unset($sequence[0]);
    unset($sequence[1]);
    unset($sequence[2]);

    if (count($sequence) == 1 && $sequence[2] == 0) {
        httpio($io_name)->relay_set_state($port, 0);
        return 0;
    }

    $mode = true;
    foreach ($sequence as $time) {
        httpio($io_name)->relay_set_state($port, $mode);
        $mode = !$mode;
        usleep($time * 1000);
    }

    return 0;
}

$rc = main($argv);
if ($rc) {
    print_help();
    exit($rc);
}

