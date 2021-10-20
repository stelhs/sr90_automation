#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';
require_once 'config.php';

require_once 'board_io_api.php';
$utility_name = $argv[0];

function print_help()
{
    global $utility_name;
    pnotice("Usage: $utility_name <io_name> <port_num> <enable_time> <disable_time> ...\n" .
             "\tStarting port state changer sequencer\n" .
             "\t\tExample:\n" .
             "\t\t\t $utility_name charge_discharge 500 500 300 500\n" .
    	"\n\n");
}


function main($argv)
{
    if (!isset($argv[1])) {
        print_help();
        return -1;
    }

    $pname = $argv[1];

    $sequence = $argv;
    unset($sequence[0]);
    unset($sequence[1]);

    if (count($sequence) == 1 && $sequence[2] == 0) {
        iop($pname)->down();
        return 0;
    }

    $mode = true;
    foreach ($sequence as $time) {
        if ($mode)
            iop($pname)->up();
        else
            iop($pname)->down();
        $mode = !$mode;
        usleep($time * 1000);
    }

    return 0;
}

exit(main($argv));


