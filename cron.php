#!/usr/bin/php
<?php
chdir(dirname($argv[0]));

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';

require_once 'boiler_api.php';
require_once 'common_lib.php';
require_once 'guard_api.php';

function print_help()
{
    global $argv;
    $utility_name = $argv[0];
    pnotice( "Usage: $utility_name <command> [args]\n" .
             "\tcommands:\n" .

             "\t\tmin [task_name] - Run all or specific 'min' tasks\n" .
             "\t\thour [task_name] - Run all or specific 'hour' tasks\n" .
             "\t\tday [task_name] - Run all or specific 'day' tasks\n" .

             "\t\tlist - print list of tasks\n" .
             "\n\n");
}

function php_err_handler($errno, $str, $file, $line) {
    $text .= sprintf("PHP %s: %s in %s:%s \n %s \n",
                     errno_to_str($errno), $str, $file, $line,
                     backtrace_to_str(1));
    plog(LOG_ERR, 'sr90:periodically', $text);
    tn()->send_to_admin("sr90:periodically: %s", $text);
}

function main($argv) {
    set_error_handler('php_err_handler');

    if (count($argv) < 2) {
        print_help();
        return;
    }

    $interval = $argv[1];
    $handler_name = NULL;
    if (isset($argv[2]))
        $handler_name = $argv[2];

    switch ($interval) {
    case "min":
    case "hour":
    case "day":
        break;
    case "list":
        pnotice("list tasks:\n");
        foreach (cron_handlers() as $handler) {
            $class = get_class($handler);
            $info = new ReflectionClass($class);
            pnotice("\t%s: %s : %s +%d\n", $handler->interval(), $handler->name(),
                    $info->getFileName(), $info->getStartLine());
        }
        return -EINVAL;
    default:
        return -EINVAL;
    }

    foreach (cron_handlers() as $handler) {
        if ($handler_name and $handler->name() != $handler_name)
            continue;

        if ($handler->interval() != $interval)
            continue;


        pnotice("run handler: %s\n", $handler->name());
        $handler->do();
    }
}

exit(main($argv));