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
    pnotice("Usage: $utility_name\n" .
    "\n\n");
}

function main($argv)
{
    iop('out_test1')->blink(1000, 500, 3);
    return;
    dump(skynet_stat_telegram());

    pnotice("List of periodicaly events:\n");
    foreach (periodically_list() as $handler) {
        $class = get_class($handler);
        $info = new ReflectionClass($class);
        pnotice("\t%s : %s +%d\n", $handler->name(),
                $info->getFileName(), $info->getStartLine());
    }

    pnotice("\nList of cron events:\n");
    foreach (cron_handlers() as $handler)
        pnotice("\t%s: %s\n", $handler->interval(), $handler->name());

    pnotice("\nList of board IO events:\n");
    foreach (io_handlers() as $handler) {
        pnotice("\t%s:\n", $handler->name());
        foreach ($handler->trigger_ports() as $f => $plist) {
            foreach ($plist as $pname => $trig_state) {
                $port = io()->port($pname);
                pnotice("\t\t%s\n", $port->str());
            }
        }
    }

    pnotice("\nList of telegram events:\n");
    foreach (telegram_handlers() as $handler)
        pnotice("\t%s\n", $handler->name());


    pnotice("\nList of SMS events:\n");
    foreach (sms_handlers() as $handler)
        pnotice("\t%s\n", $handler->name());

    pnotice("\n\n");
    return 0;
}


exit(main($argv));

