#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'config.php';
require_once 'board_io_api.php';
require_once 'sequencer_lib.php';
require_once 'common_lib.php';
require_once 'well_pump_api.php';

function print_help()
{
    global $app_name;
    pnotice("\nUsage: $app_name <command> <args>\n" .
             "\tcommands:\n" .
                 "\t\t enable: run well pump.\n" .
                 "\t\t\texample: $app_name run\n" .
                 "\t\t disable: stop well pump.\n" .
                 "\t\t\texample: $app_name stop\n" .
                 "\t\t stat: return current status.\n" .
                 "\t\t\texample: $app_name stat\n" .
                 "\n\n");
}


function main($argv)
{
    global $app_name;
    $app_name = $argv[0];

    if (!isset($argv[1]))
        return -EINVAL;

    $cmd = strtolower($argv[1]);

    switch ($cmd) {
    case "run":
        well_pump()->start();
        return 0;

    case "stop":
        well_pump()->stop();
        return 0;

    case "stat":
        $stat = well_pump()->stat();
        pnotice("Pump state: %d\n", $stat['state']);
        pnotice("Duration time: %d\n", $stat['duration']);
        return 0;

    default:
        perror("Invalid arguments\n");
        return -EINVAL;
    }

    return 0;
}


$rc = main($argv);
if ($rc) {
    print_help();
    exit($rc);
}

