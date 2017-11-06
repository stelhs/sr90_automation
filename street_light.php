#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'config.php';
require_once 'httpio_lib.php';
require_once 'guard_lib.php';
require_once 'sequencer_lib.php';
require_once 'common_lib.php';

$utility_name = $argv[0];

function print_help()
{
    global $utility_name;
    echo "Usage: $utility_name <command> <args>\n" .
             "\tcommands:\n" .
                 "\t\t enable: enable street light [timeout (in seconds)] [zone_id].\n" .
                 "\t\t\texample: $utility_name enable 30 1\n" .
                 "\t\t disable: disable street light. [zone_id]\n" .
                 "\t\t\texample: $utility_name disable 1\n" .
                 "\t\t stat: return current status.\n" .
                 "\t\t\texample: $utility_name stat\n" .
    "\n\n";
}


function main($argv)
{
    if (!isset($argv[1]))
        return -EINVAL;

    $cmd = strtolower($argv[1]);

    switch ($cmd) {
    case "enable":
        $timeout = isset($argv[2]) ? $argv[2] : 0;
        $zone_num = isset($argv[3]) ? $argv[3] : 0;
        printf("timeout = %d\n", $timeout);

        foreach (conf_street_light() as $row) {
            if ($zone_num && $zone_num != $row['zone'])
                continue;

            if ($timeout) {
                sequncer_stop($row['io'], $row['io_port']);
                sequncer_start($row['io'], $row['io_port'], [$timeout * 1000, 0]);
                continue;
            }

            $rc = httpio($row['io'])->relay_set_state($row['io_port'], 1);
            if ($rc < 0)
                perror("Can't set relay state %d\n", $row['io_port']);
        }
        return 0;

    case "disable":
        $zone_num = isset($argv[2]) ? $argv[2] : 0;

        foreach (conf_street_light() as $row) {
            if ($zone_num && $zone_num != $row['zone'])
                continue;

            $rc = httpio($row['io'])->relay_set_state($row['io_port'], 0);
            if ($rc < 0)
                perror("Can't set relay state %d\n", $row['io_port']);
        }
        return 0;

    case "stat":
        $stat = get_street_light_stat();
        foreach ($stat as $zone)
            perror("\tstree light zone %s %s\n",
                $zone['name'], ($zone['state'] == "1" ? "enable" : "disable"));
        return 0;

    default:
        perror("Invalid arguments\n");
        return -EINVAL;
    }

    return 0;
}


$rc = main($argv);
if ($rc) {
    print_help();
    exit($rc);
}

