#!/usr/bin/php
<?php 
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';
require_once 'config.php';

define("TELEGRAM_ACTIONS_DIR", "telegram_actions/");
define("MSG_LOG_LEVEL", LOG_NOTICE);

function subscribers_get_list($cmd)
{
    $list_scripts = [];
    $files = scandir(TELEGRAM_ACTIONS_DIR);

    // find brodcast subsribers
    foreach ($files as $file) {
        preg_match('/all(_\w+)?\.php/', $file, $mathes);
        if (!isset($mathes[0]) || !trim($mathes[0]))
            continue;

        $script = trim($mathes[0]);
        $list_scripts[] = $script;
    }

    // find target subscriber
    foreach ($files as $file) {
        preg_match('/' . $cmd . '(_\w+)?\.php/', $file, $mathes);
        if (!isset($mathes[0]) || !trim($mathes[0]))
            continue;

        $script = trim($mathes[0]);
        $list_scripts[] = $script;
    }

    return $list_scripts;
}


function main($argv) {
    $rc = 0;
    if (count($argv) < 4)
        return -EINVAL;

    $from_user_id = strtolower(trim($argv[1]));
    $chat_id = strtolower(trim($argv[2]));
    $msg_text = strtolower(trim($argv[3]));
    $msg_id = isset($argv[4]) ? strtolower(trim($argv[4])) : 0;
    
    $words = split_string($msg_text);
    if (!$words)
        return -EINVAL;

    $arg1 = strtolower($words[0]);
    if ($arg1 != "sky" && $arg1 != "skynet")
        return -EINVAL;
    
    $cmd = strtolower($words[1]);
    $list_subscribers = subscribers_get_list($cmd);
    if (!count($list_subscribers))
        return 0;

    foreach ($list_subscribers as $script_name) {
        $script = sprintf("%s '%s' '%s' '%s' '%s'", $script_name, 
                                      $from_user_id, $chat_id, $msg_text, $msg_id);
        dump("run " . $script . "\n");
        $ret = run_cmd(TELEGRAM_ACTIONS_DIR . $script);
        if ($ret['rc']) {
            msg_log(LOG_ERR, sprintf("script %s: return error: %s\n", 
                                     $script, $ret['log']));
            continue;
        }

        msg_log(LOG_NOTICE, sprintf("script %s: return:\n%s\n", $script, $ret['log']));
    }

    return 0;
}

return main($argv);
