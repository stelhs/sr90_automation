#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'padlock_api.php';

function print_help()
{
    global $app_name;
    echo "\nUsage: $app_name <command> <args>\n" .
             "\tcommands:\n" .
                 "\t\t open: open padlock. Args: [padlock_name] [...]\n" .
                 "\t\t\texample: $app_name open rp\n" .
                 "\t\t close: close padlock. Args: [padlock_name] [...]\n" .
                 "\t\t\texample: $app_name close sk\n" .
                 "\t\t stat: return current status.\n" .
                 "\t\t\texample: $app_name stat\n" .
    "\n\n";
}


function main($argv)
{
    global $app_name;
    $app_name = $argv[0];

    if (!isset($argv[1])) {
        print_help();
        return -EINVAL;
    }
    $cmd = strtolower($argv[1]);

    switch ($cmd) {
    case "open":
        if (isset($argv[2])) {
            $name = strtolower($argv[2]);
            padlock($name)->open();
            return 0;
        }
        padlocks_open();
        return 0;

    case "close":
        if (isset($argv[2])) {
            $name = strtolower($argv[2]);
            padlock($name)->close();
            return 0;
        }
        padlocks_close();
        return 0;

    case "stat":
        $padlocks = padlocks_stat();
        foreach ($padlocks as $padlock) {
            pnotice("\tpadlock %s '%s': %s\n",
                    $padlock['name'], $padlock['desc'],
                    ($padlock['state'] == "1" ? "open" : "close"));
        }
        return 0;

    default:
        perror("Invalid arguments\n");
        $rc = -EINVAL;
    }

    return 0;
}


exit(main($argv));

