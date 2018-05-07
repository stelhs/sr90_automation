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
    if (!isset($argv[1]))
        return json_encode(['status' => 'error']);

    parse_str($argv[1], $data);

    if ((!isset($data['io'])) || (!isset($data['port'])) || (!isset($data['state']))) {
        perror("Incorrect arguments\n");
        return -EINVAL;
    }

    $io_name = strtolower(trim($data['io']));
    $port = strtolower(trim($data['port']));
    $state = strtolower(trim($data['state']));

    if (!isset(conf_io()[$io_name])) {
        perror("module I/O %s was not found\n", $io_name);
        return -EINVAL;
    }

    if ($port < 1 || $port > 10) {
        perror("port number must be in interval [1:10]\n");
        return -EINVAL;
    }

    if ($state < 0 || $state > 1) {
        perror("state must be 0 or 1\n");
        return -EINVAL;
    }

    db()->insert('io_input_actions', ['io_name' => $io_name,
                                      'port' => $port,
                                      'state' => $state]);

    $list_subscribers = subscribers_get_list();
    if (!count($list_subscribers)) {
        perror("subscribers not found\n");
        return -1;
    }

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

    return 0;
}

exit(main($argv));