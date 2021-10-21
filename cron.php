#!/usr/bin/php
<?php
chdir(dirname($argv[0]));

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';

require_once 'boiler_api.php';
require_once 'common_lib.php';
require_once 'guard_api.php';

function php_err_handler($errno, $str, $file, $line) {
    $text .= sprintf("PHP %s: %s in %s:%s \n %s \n",
                     errno_to_str($errno), $str, $file, $line,
                     backtrace_to_str(1));
    plog(LOG_ERR, 'sr90:periodically', $text);
    tn()->send_to_admin("sr90:periodically: %s", $text);
}

function main($argv) {
    set_error_handler('php_err_handler');

    if (count($argv) < 2)
        return;

    $interval = $argv[1];

    switch ($interval) {
    case "min":
    case "hour":
    case "day":
        break;
    default:
        return -EINVAL;
    }

    foreach (cron_handlers() as $handler) {
        if ($handler->interval() != $interval)
            continue;

        pnotice("run work: %s\n", $handler->name());
        $handler->do();
    }
}

exit(main($argv));