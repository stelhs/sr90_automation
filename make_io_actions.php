#!/usr/bin/php
<?php 
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
$utility_name = $argv[0];

define("IO_ACTIONS_DIR", "io_actions/");
define("MSG_LOG_LEVEL", LOG_NOTICE);

function subscribers_get_list($action_port)
{
    $list_scripts = [];
    $files = scandir('./');
    
    // find brodcast subsribers
    foreach ($files as $file) {
        preg_match('/all_\w+\.php/', $file, $mathes);
        if (!isset($mathes[0]) || !trim($mathes[0]))
            continue;
            
        $script = trim($mathes[0]);
        $list_scripts[] = $script;
    }
    
    // find specific port subscribers
    foreach ($files as $file) {
        preg_match('/p' . $action_port . '_\w+\.php/', $file, $mathes);
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
    
    chdir(IO_ACTIONS_DIR);
    
    $list_subscribers = subscribers_get_list($action_port);
    if (!count($list_subscribers))
        return 0;

    foreach ($list_subscribers as $script_name) {
        $script = sprintf("%s %s %s", $script_name, 
                                      $action_port, $action_state);
        $ret = run_cmd('./' . $script);
                        
        if ($ret['rc']) {
            msg_log(LOG_ERR, sprintf("script %s: return error: %s\n", 
                                     $script, $ret['log']));
            continue;
        }
        
        msg_log(LOG_NOTICE, sprintf("script %s: return:\n%s\n", $script, $ret['log']));
    }
}


return main($argv);