#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'config.php';
require_once 'guard_api.php';

function print_help()
{
    global $argv;
    $utility_name = $argv[0];
    echo "Usage: $utility_name <command> <args>\n" .
             "\tcommands:\n" .
                 "\t\t run: start guard\n" .
                 "\t\t\texample: $utility_name start\n" .
                 "\t\t stop: stop guard\n" .
                 "\t\t\texample: $utility_name stop\n" .
                 "\t\t stat: Return status information about Guard system\n" .
             "\n\n";
}



function main($argv)
{
    if (!isset($argv[1])) {
        print_help();
        return -EINVAL;
    }

    $cmd = $argv[1];

    switch ($cmd) {
    case "start":
        return guard()->start('cli');

    case "stop":
        return guard()->stop('cli');

    case 'stat':
        $guard_state = guard()->stat();
        dump($guard_state);
        $stat_text = skynet_stat_sms();
        pnotice("%s\n", $stat_text);
        return 0;

    default:
        perror("Invalid arguments\n");
        return -EINVAL;
    }

}


exit(main($argv));

