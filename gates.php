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
    echo "Usage: $utility_name <command> <args>\n" .
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
                 "\n\n";
}

function main($argv)
{
    if (!isset($argv[1])) {
        print_help();
        return -EINVAL;
    }

    $enable_port = httpio_port(conf_gates()['enable_port']);
    $open_port = httpio_port(conf_gates()['open_port']);
    $open_ped_port = httpio_port(conf_gates()['open_ped_port']);
    $close_port = httpio_port(conf_gates()['close_port']);
    $state_port = httpio_port(conf_gates()['state_port']);

    $cmd = strtolower($argv[1]);

    switch ($cmd) {
    case "enable":
        gates_power_enable();
        printf("Gates power enabled\n");
        return 0;

    case "disable":
        $rc = gates_power_disable();
        if ($rc) {
            printf("Error: Gates is not closed\n");
            return $rc;
        }
        printf("Gates power disabled\n");
        return 0;

    case "open":
        $rc = gates_open();
        if ($rc) {
            printf("Error: Gates power is disabled\n");
            return $rc;
        }
        printf("Gates start to opening\n");
        return 0;

    case "open-ped":
        $rc = gates_open_ped();
        if ($rc) {
            printf("Error: Gates power is disabled\n");
            return $rc;
        }
        printf("Gates start to opening for pedestrian\n");
        return 0;

    case "close":
        $rc = gates_close();
        if ($rc) {
            printf("Error: Gates power is disabled\n");
            return $rc;
        }
        printf("Gates start to closing\n");
        return 0;

    case "close-sync":
        printf("Gates start to closing\n");
        $rc = gates_close_sync();

        if ($rc == -EBUSY) {
            printf("Error: Gates power is disabled\n");
            return $rc;
        }

        if ($rc == -ECONNFAIL) {
            printf("Error: Timeout was expired. Gates is not closed\n");
            return $rc;
        }

        printf("Gates successfully closed\n");
        return 0;

    case "stat":
        $stat = gates_stat();
        dump($stat);
        printf("Gates power is %s\n", $stat['power']);
        printf("Gates is %s\n", $stat['gates']);
        return 0;

    default:
        perror("Invalid arguments\n");
        return -EINVAL;
    }

    return 0;
}


$rc = main($argv);
exit($rc);

