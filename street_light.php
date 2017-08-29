#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'config.php';
require_once 'mod_io_lib.php';
require_once 'guard_lib.php';
require_once 'sequencer_lib.php';
require_once 'server_control_lib.php';

$utility_name = $argv[0];

function print_help()
{
    global $utility_name;
    echo "Usage: $utility_name <command> <args>\n" .
             "\tcommands:\n" .
                 "\t\t enable: enable street light <timeout>.\n" . 
                 "\t\t\texample: $utility_name enable 30\n" .
                 "\t\t disable: disable street light.\n" . 
                 "\t\t\texample: $utility_name disable\n" .
                 "\t\t stat: return current status.\n" . 
                 "\t\t\texample: $utility_name stat\n" .
    "\n\n";
}


function main($argv)
{
    $rc = 0;

    if (!isset($argv[1]))
        return -EINVAL;
    $cmd = strtolower($argv[1]);

    $db = new Database;
    $rc = $db->connect(conf_db());
    if ($rc) {
        printf("can't connect to database");
        return -EBASE;
    }

    $mio = new Mod_io($db);

    switch ($cmd) {
    case "enable":
        $padlock_num = isset($argv[2]) ? $argv[2] : 0;
        $timeout = isset($argv[3]) ? $argv[3] : 0;

        foreach (conf_street_light() as $row) {
            if ($padlock_num && $padlock_num != $row['zone'])
                continue;

            if ($timeout) {
                sequncer_stop($row['io_port']);
                sequncer_start($row['io_port'], [$timeout * 1000, 0]);
                continue;
            }

            $rc = $mio->relay_set_state($row['io_port'], 1);
            if ($rc < 0)
                printf("Can't set relay state %d\n", $row['io_port']);
        }
        goto out;

    case "disable":
        $padlock_num = isset($argv[2]) ? $argv[2] : 0;

        foreach (conf_street_light() as $row) {
            if ($padlock_num && $padlock_num != $row['zone'])
                continue;

            $rc = $mio->relay_set_state($row['io_port'], 0);
            if ($rc < 0)
                printf("Can't set relay state %d\n", $row['io_port']);
        }
        goto out;

    case "stat":
        $stat = get_street_light_stat($db);
        foreach ($stat as $zone)
            printf("\tstree light zone %s %s\n", $zone['name'], ($zone['state'] == "1" ? "enable" : "disable"));

        break;

    default:
        printf("Invalid arguments\n");
        $rc = -EINVAL;
    }

out:
    $db->close();
    return $rc;
}


$rc = main($argv);
if ($rc) {
    print_help();
}

