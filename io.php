#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'config.php';
require_once 'board_io_api.php';
$app_name = $argv[0];

function print_help()
{
    global $app_name;
    perror("Usage: $app_name <command> <args>\n" .
             "\tcommands:\n" .
                 "\t\t <port_name/port_id> [up|down]\n" .
                 "\t\t\texample: $app_name ext_power \n" .
                 "\t\t\texample: $app_name voice_power up \n" .
                 "\t\t\texample: $app_name sbio1.out.1 down \n" .
                 "\t\t\texample: $app_name sbio2.out \n" .
                 "\t\t\texample: $app_name sbio3 \n" .

                 "\t\t wdt_on: enable hardware watchdog\n" .
                 "\t\t wdt_off: disable hardware watchdog\n" .
                 "\t\t wdt_reset: reset hardware watchdog\n" .
                 "\t\t trig <port_name> <state>: triggered port for debuging purposes\n" .
             "\n\n");
}



function main($argv)
{
    global $app_name;
    $app_name = "sr90:".basename($argv[0]);

    if (!isset($argv[1])) {
        print_help();
        return -EINVAL;
    }

    $cmd = $argv[1];

    switch ($cmd) {
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

    case 'trig':
        if (!isset($argv[2]) || !isset($argv[3])) {
            perror("For trig command needs 'pname' and 'state'\n");
            return -EINVAL;
        }

        $pname = $argv[2];
        if (!port_info($pname)) {
            perror("Port name '%s' has not registred\n", $pname);
            return -EINVAL;
        }

        $state = $argv[3];
        if ($state < 0 || $state > 1) {
            perror("Incorrect port state %d. Port state must be 0 or 1\n", $state);
            return -EINVAL;
        }

        $info = port_info($pname);

        $handlers = trig_io_event($pname, $state);
        if (!$handlers) {
            pnotice("\n%s is not triggered\n", $info['str']);
            return 0;
        }

        $names = [];
        foreach ($handlers as $handler)
            $names[] = $handler->name();

        pnotice("\n%s has triggered for: \n\t%s\n",
                $info['str'], array_to_string($names, ", "));
        return 0;

    default:
        $pname = $cmd;
        $info = port_info($pname);
        if ($info) {
            $op = '';
            if (isset($argv[2]))
                $op = $argv[2];

            if ($info['mode'] == 'in') {
                $s = iop($pname)->state();
                printf("%s return %d\n", $info['str'], $s);
                return 0;
            }

            switch ($op) {
            case 'up':
                printf("%s set to 1\n", $info['str']);
                return iop($pname)->up();

            case 'down':
                printf("%s set to 0\n", $info['str']);
                return iop($pname)->down();

            default:
                $s = iop($pname)->state();
                printf("%s return %d\n", $info['str'], $s);
                return 0;
            }
            return -EINVAL;
        }

        $id = $cmd;
        $io_name = NULL;
        $mode = NULL;
        $port = NULL;

        preg_match('/(\w+)\.(\w+).(\d+)/i', $id, $m);
        if (count($m) == 4) {
            $io_name = $m[1];
            $mode = $m[2];
            $port = $m[3];
        } else {
            preg_match('/(\w+)\.(\w+)/i', $id, $m);
            if (count($m) == 3) {
                $io_name = $m[1];
                $mode = $m[2];
            } else
                $io_name = $id;
        }

        if (!isset(conf_io()[$io_name])) {
            perror("Incorrect board name\n");
            return -EINVAL;
        }

        $board_io = board_io($io_name,
                             conf_io()[$io_name]['ip_addr'],
                             conf_io()[$io_name]['tcp_port']);
        if (!$board_io) {
            perror("Incorrect board name\n");
            return -EINVAL;
        }

        if (!$port) {
            if ($mode) {
                if (!isset(conf_io()[$io_name][$mode])){
                    perror("Incorrect mode. Must be 'in' or 'out'\n");
                    return -EINVAL;
                }

                foreach (conf_io()[$io_name][$mode] as $pn => $pname) {
                    switch ($mode) {
                    case 'in':
                        $p = new Board_io_in($board_io, $pn);
                        break;
                    case 'out':
                        $p = new Board_io_out($board_io, $pn);
                        break;
                    default:
                        perror("Incorrect IO mode '%s'\n", $mode);
                        return -EINVAL;
                    }
                    $s = $p->state();
                    printf("%s.%s.%d return %d\n",
                            $io_name, $mode, $pn, $s);
                }
                return 0;
            }

            foreach (conf_io()[$io_name]['in'] as $pn => $pname) {
                $p = new Board_io_in($board_io, $pn);
                $s = $p->state();
                printf("%s.in.%d return %d\n",
                        $io_name, $pn, $s);
            }
            printf("\n");
            foreach (conf_io()[$io_name]['out'] as $pn => $pname) {
                $p = new Board_io_out($board_io, $pn);
                $s = $p->state();
                printf("%s.out.%d return %d\n",
                        $io_name, $pn, $s);
            }
            return 0;
        }

        $op = '';
        if (isset($argv[2]))
            $op = $argv[2];

        $pname = conf_io()[$io_name][$mode][$port];
        switch ($op) {
        case 'up':
        case 'down':
            if ($mode != 'out') {
                perror("Operation up/down compatibility only with 'out' ports\n");
                return -EINVAL;
            }
            $p = new Board_io_out($board_io, $port);
            if ($op == 'up') {
                $p->up();
                printf("%s set to 1\n",
                        port_str($pname, $io_name, 'out', $port));
                return 0;
            }
            $p->down();
                printf("%s set to 0\n",
                        port_str($pname, $io_name, 'out', $port));
            return 0;

        default:
            switch ($mode) {
            case 'in':
                $p = new Board_io_in($board_io, $port);
                break;
            case 'out':
                $p = new Board_io_out($board_io, $port);
                break;
            default:
                perror("Incorrect IO mode '%s'\n", $mode);
                return -EINVAL;
            }
            $s = $p->state();
            printf("%s return %d\n",
                    port_str($pname, $io_name, $mode, $port), $s);
            return 0;
        }
        return -EINVAL;

    }
}


$rc = main($argv);
if ($rc) {

    exit($rc);
}
