#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'config.php';
require_once 'common_lib.php';

$utility_name = $argv[0];

function print_help()
{
    global $utility_name;
    echo "Usage: $utility_name\n" .
    "\n\n";
}


function main($argv)
{
    dump(skynet_stat());

    printf("List of periodicaly events:\n");
    foreach (periodically_list() as $handler)
        printf("\t%s\n", $handler->name());

    printf("\nList of cron events:\n");
    foreach (cron_handlers() as $handler)
        printf("\t%s: %s\n", $handler->interval(), $handler->name());

    printf("\nList of board IO events:\n");
    foreach (io_handlers() as $handler) {
        printf("\t%s:\n", $handler->name());
        foreach ($handler->trigger_ports() as $port_name => $trig_state) {
            $info = port_info($port_name);
            printf("\t\t%s\n", $info['str']);
        }
    }

    printf("\nList of telegram events:\n");
    foreach (telegram_handlers() as $handler)
        printf("\t%s\n", $handler->name());


    printf("\nList of SMS events:\n");
    foreach (sms_handlers() as $handler)
        printf("\t%s\n", $handler->name());

    printf("\n\n");
    return 0;
}


exit(main($argv));

