#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'config.php';
require_once 'usio_lib.php';
require_once 'httpio_lib.php';
$utility_name = $argv[0];

function print_help()
{
    global $utility_name;
    perror("Usage: $utility_name <command> <args>\n" .
             "\tcommands:\n" .
                 "\t\t relay_set: set relay output state. Args: <io_name> <port_num>\n" .
                 "\t\t\texample: $utility_name relay_set usio1 4 1\n" .
                 "\t\t relay_get: get relay state. Args: <io_name> <port_num>\n" .
                 "\t\t\texample: $utility_name relay_get usio1 3\n" .
                 "\t\t input: get input state. Args: <io_name> <port_num>\n" .
                 "\t\t\texample: $utility_name input usio1 3\n" .
                 "\t\t make_action: Generate I/O action. Args: <io_name> <port_num> <port_state>\n" .
                 "\t\t\texample: $utility_name make_action usio1 2 0\n" .

                 "\t\t wdt_on: enable hardware watchdog\n" .
                 "\t\t wdt_off: disable hardware watchdog\n" .
                 "\t\t wdt_reset: reset hardware watchdog\n" .
             "\n\n");
}



function main($argv)
{
    if (!isset($argv[1]))
        return -EINVAL;

    $cmd = $argv[1];

    switch ($cmd) {
    case 'relay_set':
        if (!isset($argv[4])) {
            perror("Invalid arguments: command arguments is not set\n");
            return -EINVAL;
        }

        $io_name = $argv[2];
        $port = $argv[3];
        $state = $argv[4];

        if ($port < 1 || $port > 7) {
            perror("Invalid arguments: port is not correct. port > 0 and port <= 7\n");
            return -EINVAL;
        }

        if ($state < 0 || $state > 1) {
            perror("Invalid arguments: state is not correct. state may be 0 or 1\n");
            return -EINVAL;
        }

        $rc = httpio($io_name)->relay_set_state($port, $state);
        if ($rc < 0) {
            perror("Can't set relay state\n");
        }
        return 0;

    case 'relay_get':
        if (!isset($argv[3])) {
            perror("Invalid arguments: command arguments is not set\n");
            return -EINVAL;
        }

        $io_name = $argv[2];
        $port = $argv[3];

        if ($port < 1 || $port > 10) {
            perror("Invalid arguments: port is not correct. port > 0 and port <= 7\n");
            return -EINVAL;
        }

        $rc = httpio($io_name)->relay_get_state($port);
        if ($rc < 0) {
            perror("Can't get relay state\n");
        }
        perror("Relay port %d = %d\n", $port, $rc);
        return 0;

    case 'input':
        if (!isset($argv[3])) {
            perror("Invalid arguments: command arguments is not set\n");
            return -EINVAL;
        }

        $io_name = $argv[2];
        $port = $argv[3];

        if ($port < 1 || $port > 10) {
            perror("Invalid arguments: port is not correct. port > 0 and port <= 10\n");
            return -EINVAL;
        }

        $rc = httpio($io_name)->input_get_state($port);
        if ($rc < 0) {
            perror("Can't get input state\n");
        }
        perror("Input port %d = %d\n", $port, $rc);
        return 0;

    case 'wdt_on':
        $rc = usio()->wdt_on();
        if ($rc < 0)
            perror("Can't enable WDT\n");
        return 0;

    case 'wdt_off':
        $rc = usio()->wdt_off();
        if ($rc < 0)
            perror("Can't disable WDT\n");
        return 0;

    case 'wdt_reset':
        $rc = usio()->wdt_reset();
        return 0;

    case 'make_action':
        $io_name = strtolower($argv[2]);
        $port = $argv[3];
        $state = $argv[4];

        if (!isset(conf_io()[$io_name])) {
            perror("module I/O %s was not found\n", $io_name);
            return -EINVAL;
        }

        if ($port < 1 || $port > 10) {
            perror("port number must be in interval [1:10]\n");
            return -EINVAL;
        }

        if ($state < 0 || $state > 1) {
            perror("state must be 0 or 1\n");
            return -EINVAL;
        }

        $content = file_get_contents(sprintf("http://localhost:400/ioserver" .
                                             "?io=%s&port=%d&state=%d",
                                             $io_name,
                                             $port,
                                             $state));
        if (!$content) {
            perror("Returned content is empty\n");
            return -ECONNFAIL;
        }

        $ret = json_decode($content, true);
        if (!$ret) {
            perror("Can't JSON decoded returned content\n");
            return -EPARSE;
        }

        if ($ret['status'] != 'ok') {
            pnotice("Error: %s", $ret['reason']);
            return $ret['status'];
        }

        pnotice("http return: %s\n", $ret['log']);
    }
}


$rc = main($argv);
if ($rc) {
    print_help();
    exit($rc);
}
