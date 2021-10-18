#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'lighters_api.php';

function print_help()
{
    global $app_name;
    echo "\nUsage: $app_name <command> <args>\n" .
             "\tcommands:\n" .
                 "\t\t enable: enable lighter. Args: [lighter_name] [...]\n" .
                 "\t\t\texample: $app_name enable workshop\n" .
                 "\t\t disable: disable lighter. Args: [lighter_name] [...]\n" .
                 "\t\t\texample: $app_name disable workshop\n" .
                 "\t\t stat: return current status.\n" .
                 "\t\t\texample: $app_name stat\n" .
    "\n\n";
}


function main($argv)
{
    global $app_name;
    $app_name = $argv[0];

    if (!isset($argv[1])) {
        print_help();
        return -EINVAL;
    }
    $cmd = strtolower($argv[1]);

    switch ($cmd) {
    case "enable":
        if (isset($argv[2])) {
            $name = strtolower($argv[2]);
            lighter($name)->enable();
            return 0;
        }
        street_lights_enable();
        return 0;

    case "disable":
        if (isset($argv[2])) {
            $name = strtolower($argv[2]);
            lighter($name)->disable();
            return 0;
        }
        street_lights_disable();
        return 0;

    case "stat":
        $lighters = street_lights_stat();
        foreach ($lighters as $lighter) {
            pnotice("\tlighter %s '%s': %s\n",
                    $lighter['name'], $lighter['desc'],
                    ($lighter['state'] == "1" ? "enabled" : "disabled"));
        }
        return 0;

    default:
        perror("Invalid arguments\n");
        $rc = -EINVAL;
    }

    return 0;
}


exit(main($argv));

