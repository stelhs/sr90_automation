#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once 'common_lib.php';

define("IO_ACTIONS_DIR", "io_actions/");


function subscribers_get_list()
{
    $list_scripts = [];
    $files = scandir(IO_ACTIONS_DIR);

    // find brodcast subsribers
    foreach ($files as $file) {
        preg_match('/\w+\.php/', $file, $mathes);
        if (!isset($mathes[0]) || !trim($mathes[0]))
            continue;

            $script = trim($mathes[0]);
            $list_scripts[] = $script;
    }

    return $list_scripts;
}

function main($argv)
{
    chdir(dirname($argv[0]));
    $list_subscribers = subscribers_get_list();
    if (!count($list_subscribers)) {
        perror("subscribers not found\n");
        return -1;
    }

    foreach (conf_io() as $io_name => $io_info) {
        if (isset($argv[1]) && $io_name != $argv[1])
            continue;
        for ($port = 1; $port < $io_info['in_ports']; $port++) {
            $state = httpio($io_name)->input_get_state($port);
            db()->insert('io_input_actions',
                         ['io_name' => $io_name,
                          'port' => $port,
                          'state' => $state]);

            $log = '';
            foreach ($list_subscribers as $script_name) {
                $script = sprintf("%s %s %s %s",
                    $script_name, $io_name, $port, $state);
                pnotice("run I/O script %s\n", $script);
                $ret = run_cmd(IO_ACTIONS_DIR . $script);

                if ($ret['rc']) {
                    pnotice("script %s: return error: %s\n",
                        $script, $ret['log']);
                    continue;
                }

                if ($ret['log'])
                    pnotice("script %s return: %s\n", $script, $ret['log']);
            }
        }
    }
    return 0;
}

exit(main($argv));
