#!/usr/bin/php
<?php 
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
$utility_name = $argv[0];

define("IO_ACTIONS_DIR", "io_actions/");

function subscribers_get_list($action_port)
{
    $list_scripts = [];
    $files = scandir(IO_ACTIONS_DIR);
    foreach ($files as $file) {
        preg_match('/p' . $action_port . '\w+\.php/', $file, $mathes);
        if (!isset($mathes[0]) || !trim($mathes[0]))
            continue;
            
        $script = trim($mathes[0]);
        $list_scripts[] = $script;
    }
    return $list_scripts;
}

function main($argv) {
    if (count($argv) < 3)
        return;
    
    $action_port = $argv[1];
    $action_state = $argv[2];
    
    $list_subscribers = subscribers_get_list($action_port);
    if (!count($list_subscribers))
        return 0;

    foreach ($list_subscribers as $script_name) {
        $script = sprintf(IO_ACTIONS_DIR . "%s %s %s", 
                          $script_name, $action_port, $action_state);
        $ret = run_cmd($script);
                        
        if ($ret['rc'])
            msg_log(LOG_ERR, sprintf("script %s return error %s\n", 
                                     $script, $ret['log']));
    }
}


return main($argv);