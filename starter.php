#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

$utility_name = $argv[0];

declare(ticks=1);

function print_help()
{
    global $utility_name;
    echo "Usage: $utility_name <timeout> <cmd>\n" .
             "\t timeout: timeout in seconds.\n" . 
             "\t cmd: command for run\n" . 
    "\t\texample: $utility_name 15 ps -aux\n" .
    "\n\n";
}

function signal_handler($signo)
{
    if ($signo != SIGCHLD)
        return;

    printf("children is finished %d\n", $signo);
    exit;
}

function main($argv)
{
    $rc = 0;

    if (!isset($argv[1]))
        return -EINVAL;

    $timeout = $argv[1];

    pcntl_signal(SIGCHLD, "signal_handler");

    unset($argv[0]);
    unset($argv[1]);
    printf("run: %s\n", array_to_string($argv, ' '));
    $pid = run_cmd(array_to_string($argv, ' '), true);
    printf("pid = %d\n", $pid);

    $end_time = time() + $timeout;
    printf("timeout = %d\n", $timeout);
    while (time() < $end_time);
    printf("kill %d\n", $pid);
    run_cmd(sprintf('kill -- -$(ps -o pgid= %d | grep -o [0-9]*)', $pid));
    posix_kill($pid, SIGKILL);

out:
    return $rc;
}


$rc = main($argv);
if ($rc) {
    print_help();
}

