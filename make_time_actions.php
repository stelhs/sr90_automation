#!/usr/bin/php
<?php 
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
$utility_name = $argv[0];

define("IO_ACTIONS_DIR", "time_actions/");


function subscribers_get_list($interval)
{
    $list_scripts = [];
    $files = scandir(IO_ACTIONS_DIR);
    
    // find brodcast subsribers
    foreach ($files as $file) {
        preg_match('/' . $interval . '_\w+\.php/', $file, $mathes);
        if (!isset($mathes[0]) || !trim($mathes[0]))
            continue;
            
        $script = trim($mathes[0]);
        $list_scripts[] = $script;
    }
    
    return $list_scripts;
}


function main($argv) {
    if (count($argv) < 2)
        return;
    
    $interval = $argv[1];
    
    $ok = false;
    switch ($interval) {
    case "sec":
    case "min":
    case "hour":
    case "day":
        $ok = true;
        break;
    default:
        return -EINVAL;
    }
    
    $list_subscribers = subscribers_get_list($interval);
    if (!count($list_subscribers))
        return 0;

    foreach ($list_subscribers as $script_name) {
        $script = sprintf("%s", $script_name);
        $ret = run_cmd(IO_ACTIONS_DIR . $script);
                        
        if ($ret['rc']) {
            msg_log(LOG_ERR, sprintf("script %s: return error: %s\n", 
                                     $script, $ret['log']));
            continue;
        }
        
        msg_log(LOG_NOTICE, sprintf("script %s: return:\n%s\n", $script, $ret['log']));
    }
}


return main($argv);