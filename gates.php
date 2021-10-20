#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'config.php';
require_once 'gates_api.php';

$utility_name = $argv[0];

function print_help()
{
    global $utility_name;
    pnotice("Usage: $utility_name <command> <args>\n" .
             "\tcommands:\n" .
                 "\t\t enable: enable gates power.\n" .
                 "\t\t\texample: $utility_name enable\n" .
                 "\t\t disable: disable gates power.\n" .
                 "\t\t\texample: $utility_name disable\n" .
                 "\t\t open: open gates.\n" .
                 "\t\t\texample: $utility_name open\n" .
                 "\t\t open-ped: open gates for pedestrian.\n" .
                 "\t\t\texample: $utility_name open-ped\n" .
                 "\t\t close: close gates.\n" .
                 "\t\t\texample: $utility_name close\n" .
                 "\t\t close-sync: close gates and wait finish.\n" .
                 "\t\t\texample: $utility_name close-sync\n" .
                 "\t\t stat: return current gates status.\n" .
                 "\t\t\texample: $utility_name stat\n" .
                 "\n\n");
}

function main($argv)
{
    if (!isset($argv[1])) {
        print_help();
        return -EINVAL;
    }

    $cmd = strtolower($argv[1]);

    switch ($cmd) {
    case "enable":
        gates()->power_enable();
        pnotice("Gates power enabled\n");
        return 0;

    case "disable":
        $rc = gates()->power_disable();
        if ($rc) {
            pnotice("Error: Can't stop power: gates is not closed\n");
            return $rc;
        }
        pnotice("Gates power disabled\n");
        return 0;

    case "open":
        $rc = gates()->open();
        if ($rc) {
            pnotice("Error: Gates power is disabled\n");
            return $rc;
        }
        pnotice("Gates start to opening\n");
        return 0;

    case "open-ped":
        $rc = gates()->open_ped();
        if ($rc) {
            pnotice("Error: Gates power is disabled\n");
            return $rc;
        }
        pnotice("Gates start to opening for pedestrian\n");
        return 0;

    case "close":
        $rc = gates()->close();
        if ($rc) {
            pnotice("Error: Gates power is disabled\n");
            return $rc;
        }
        pnotice("Gates start to closing\n");
        return 0;

    case "close-sync":
        pnotice("Gates start to closing\n");
        $rc = gates()->close_sync();

        if ($rc == -EBUSY) {
            pnotice("Error: Gates power is disabled\n");
            return $rc;
        }

        if ($rc == -ECONNFAIL) {
            pnotice("Error: Timeout was expired. Gates is not closed\n");
            return $rc;
        }

        pnotice("Gates successfully closed\n");
        return 0;

    case "stat":
        $stat = gates()->stat();
        dump($stat);
        pnotice("Gates power is %s\n", $stat['power']);
        pnotice("Gates is %s\n", $stat['gates']);
        return 0;

    default:
        perror("Invalid arguments\n");
        return -EINVAL;
    }

    return 0;
}


$rc = main($argv);
exit($rc);
