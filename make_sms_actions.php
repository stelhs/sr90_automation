#!/usr/bin/php
<?php 
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
$utility_name = $argv[0];

define("IO_ACTIONS_DIR", "sms_actions/");


function subscribers_get_list()
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
    
    return $list_scripts;
}


function main($argv) {
    if (count($argv) < 4)
        return;
    
    $sms_date = $argv[1];
    $phone = $argv[2];
    $sms_text = $argv[3];
    
    chdir(IO_ACTIONS_DIR);
    
    $list_subscribers = subscribers_get_list();
    if (!count($list_subscribers))
        return 0;

    foreach ($list_subscribers as $script_name) {
        $script = sprintf("%s '%s' '%s' '%s'", $script_name, 
                                      $sms_date, $phone, $sms_text);
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