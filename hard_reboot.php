#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';
require_once 'common_lib.php';
require_once 'config.php';
require_once 'usio_lib.php';
require_once 'board_io_api.php';
require_once 'power_api.php';



function print_help()
{
    global $argv;
    $utility_name = $argv[0];
    pnotice("Hard reboot all devices\n\n");
}


function main($argv)
{
    io()->board('usio1')->wdt_off();
    iop('ups_break_power')->up();
    iop('battery_relay')->up();
    power()->halt_all_systems();
    return 0;
}

$rc = main($argv);
if ($rc) {
    print_help();
    exit($rc);
}
