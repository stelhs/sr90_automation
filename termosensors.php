#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'config.php';
require_once 'termosensors_api.php';

function print_help()
{
    global $app_name;
    pnotice("\nUsage: $app_name <command> <args>\n" .
             "\tcommands:\n" .
                 "\t\t\texample: $app_name run\n");
}


function main($argv)
{
    global $app_name;
    $app_name = $argv[0];

    foreach (termosensors()->list() as $t) {
        printf("%s (%s): %.2f\n", $t->name(), $t->description(), $t->t());
    }


    return 0;
}


$rc = main($argv);
if ($rc) {
    print_help();
    exit($rc);
}

