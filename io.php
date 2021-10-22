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

                 "\t\t list: listing all IO ports and handlers\n" .

                 "\t\t trig <in_port_name> <state>: emulate triggering in port\n" .
                 "\t\t\texample: $app_name trig vru_door 0\n" .

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
        $rc = io()->board('usio1')->wdt_on();
        if ($rc < 0)
            perror("Can't enable WDT\n");
        return 0;

    case 'wdt_off':
        $rc = io()->board('usio1')->wdt_off();
        if ($rc < 0)
            perror("Can't disable WDT\n");
        return 0;

    case 'wdt_reset':
        $rc = io()->board('usio1')->wdt_reset();
        return 0;

    case 'list':
        pnotice("List in ports:\n");
        foreach (io()->ports('in') as $port)
            pnotice("\t%s\n", $port->str());

        pnotice("\nList out ports:\n");
        foreach (io()->ports('out') as $port)
            pnotice("\t%s\n", $port->str());

        pnotice("\nList IO handlers:\n");
        foreach (io_handlers() as $handler) {
            $class = get_class($handler);
            $info = new ReflectionClass($class);
            pnotice("\t%s : %s +%d\n", $handler->name(),
                $info->getFileName(), $info->getStartLine());
            foreach ($handler->trigger_ports() as $method => $plist) {
                pnotice("\t\t%s:\n", $method);
                foreach ($plist as $pname => $trig_val)
                    pnotice("\t\t\t%s: %d\n", $pname, $trig_val);
            }
            pnotice("\n");
        }
        return 0;

    case 'trig':
        if (!isset($argv[2]) || !isset($argv[3])) {
            perror("For trig command needs 'pname' and 'state'\n");
            return -EINVAL;
        }

        $pname = $argv[2];
        $port = io()->port($pname);
        if (!$port) {
            perror("Port name '%s' has not registred\n", $port->name());
            return -EINVAL;
        }

        $state = $argv[3];
        if ($state < 0 || $state > 1) {
            perror("Incorrect port state %d. Port state must be 0 or 1\n", $state);
            return -EINVAL;
        }

        $handlers = io()->trig_event($port, $state);
        if (!$handlers) {
            pnotice("\n%s is not triggered\n", $port->str());
            return 0;
        }

        $names = [];
        foreach ($handlers as $handler)
            $names[] = $handler->name();

        pnotice("\n%s has triggered for: \n\t%s\n",
                $port->str(), array_to_string($names, ", "));
        return 0;

    default:
        $pname = $cmd;
        $port = io()->port($pname);
        if ($port) {
            $op = '';
            if (isset($argv[2]))
                $op = $argv[2];

            switch ($op) {
            case 'up':
                if ($port->mode() != 'out') {
                    perror("port %s can't support method 'up'\n", $port->str());
                    return;
                }
                pnotice("%s set to 1\n", $port->str());
                return $port->up();

            case 'down':
                if ($port->mode() != 'out') {
                    perror("port %s can't support method 'down'\n", $port->str());
                    return;
                }
                pnotice("%s set to 0\n", $port->str());
                return $port->down();

            default:
                $s = $port->state()[0];
                pnotice("%s return %d\n", $port->str(), $s);
                return 0;
            }
            return -EINVAL;
        }

        $id = $cmd;
        $io_name = NULL;
        $mode = NULL;
        $pn = NULL;

        preg_match('/(\w+)\.(\w+).(\d+)/i', $id, $m);
        if (count($m) == 4) {
            $io_name = $m[1];
            $mode = $m[2];
            $pn = $m[3];
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

        if (!isset(conf_io()[$io_name])) {
            perror("Incorrect board name\n");
            return -EINVAL;
        }

        if (!$pn) {
            if ($mode) {
                if (!isset(conf_io()[$io_name][$mode])){
                    perror("Incorrect mode. Must be 'in' or 'out'\n");
                    return -EINVAL;
                }

                foreach (conf_io()[$io_name][$mode] as $pn => $pname) {
                    $port = io()->port_by_addr($io_name, $mode, $pn);
                    $s = $port->state()[0];
                    pnotice("%s return %d\n", $port->str(), $s);
                }
                return 0;
            }

            foreach (conf_io()[$io_name]['in'] as $pn => $pname) {
                $port = io()->port_by_addr($io_name, 'in', $pn);
                $s = $port->state()[0];
                pnotice("%s return %d\n", $port->str(), $s);
            }
            pnotice("\n");
            foreach (conf_io()[$io_name]['out'] as $pn => $pname) {
                $port = io()->port_by_addr($io_name, 'out', $pn);
                $s = $port->state()[0];
                pnotice("%s return %d\n", $port->str(), $s);
            }
            return 0;
        }

        $op = '';
        if (isset($argv[2]))
            $op = $argv[2];

        $port = io()->port_by_addr($io_name, $mode, $pn);
        switch ($op) {
        case 'up':
        case 'down':
            if ($mode != 'out') {
                perror("Operation up/down compatibility only with 'out' ports\n");
                return -EINVAL;
            }
            if ($op == 'up') {
                $port->up();
                pnotice("%s set to 1\n", $port->str());
                return 0;
            }
            $port->down();
                pnotice("%s set to 0\n", $port->str());
            return 0;

        default:
            $s = $port->state()[0];
            pnotice("%s return %d\n", $port->str(), $s);
            return 0;
        }
        return -EINVAL;
    }
}


$rc = main($argv);
if ($rc) {

    exit($rc);
}
