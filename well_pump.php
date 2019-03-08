#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'config.php';
require_once 'httpio_lib.php';
require_once 'guard_lib.php';
require_once 'sequencer_lib.php';
require_once 'common_lib.php';

$utility_name = $argv[0];

function print_help()
{
    global $utility_name;
    echo "Usage: $utility_name <command> <args>\n" .
             "\tcommands:\n" .
                 "\t\t enable: enable well pump.\n" .
                 "\t\t\texample: $utility_name enable\n" .
                 "\t\t disable: disable well pump.\n" .
                 "\t\t\texample: $utility_name disable\n" .
                 "\t\t stat: return current status.\n" .
                 "\t\t\texample: $utility_name stat\n" .
                 "\t\t duration: return pumping duration in seconds or 0 if pump was disabled.\n" .
                 "\t\t\texample: $utility_name duration\n" .
                 "\n\n";
}
define("PUMP_STATE_FILE", "/tmp/well_pump_enable");


function main($argv)
{
    if (!isset($argv[1]))
        return -EINVAL;

    $pump_port = httpio_port(conf_water()['well_pump_enable_port']);
    $cmd = strtolower($argv[1]);

    switch ($cmd) {
    case "enable":
        @file_put_contents(PUMP_STATE_FILE, time());
        $pump_port->set(1);
        printf("Pump enabled\n");
        return 0;

    case "disable":
        @unlink(PUMP_STATE_FILE);
        $pump_port->set(0);
        printf("Pump disabled\n");
        return 0;

    case "stat":
        printf("Pump state = %d\n", $pump_port->get());
        return 0;

    case "duration":
        @$enable_time = file_get_contents(PUMP_STATE_FILE);
        if (!$enable_time) {
            printf("0\n");
            return;
        }

        printf("%d\n", time() - $enable_time);
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

