#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';
require_once 'common_lib.php';
require_once 'config.php';
require_once 'usio_lib.php';



function print_help()
{
    global $argv;
    $utility_name = $argv[0];
    echo "Hard reboot all devices\n\n";
}


function main($argv)
{
    $stop_ups_power_port = httpio_port(conf_ups()['stop_ups_power_port']);
    $stop_ups_battery_port = httpio_port(conf_ups()['stop_ups_battery_port']);

    usio()->wdt_off();
    $stop_ups_power_port->set(1);
    $stop_ups_battery_port->set(1);
    halt_all_systems();
    return 0;
}

$rc = main($argv);
if ($rc) {
    print_help();
    exit($rc);
}
