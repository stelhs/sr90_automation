#!/usr/bin/php
<?php 
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';
require_once 'config.php';
require_once 'server_control_lib.php';

define("SMS_ACTIONS_DIR", "sms_actions/");


function subscribers_get_list($cmd)
{
    $list_scripts = [];
    $files = scandir(SMS_ACTIONS_DIR);

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
    if (count($argv) < 4)
        return -EINVAL;

    $db = new Database;
    $rc = $db->connect(conf_db());
    if ($rc) {
        printf("can't connect to database");
        return -EBASE;
    }

    $sms_date = $argv[1];
    $phone = $argv[2];
    $sms_text = $argv[3];

    $user_id = 0;
    $user = user_get_by_phone($db, $phone);
    if ($user) {
        $user_id = $user['id'];
        if (!$user && !$user['serv_control'])
            $user_id = 0;
    }

    $db->insert('incomming_sms', array('phone' => $phone,
                                       'text' => $sms_text,
                                       'received_date' => $sms_date));
    $words = split_string($sms_text);
    if (!$words)
        return -EINVAL;

    $cmd = $words[0];

    $list_subscribers = subscribers_get_list($cmd);
    if (!count($list_subscribers))
        return 0;

    foreach ($list_subscribers as $script_name) {
        $script = sprintf("%s '%s' '%s' '%s'", $script_name, 
                                      $sms_date, $user_id, $sms_text);
        $ret = run_cmd(SMS_ACTIONS_DIR . $script);
        if ($ret['rc']) {
            msg_log(LOG_ERR, sprintf("script %s: return error: %s\n", 
                                     $script, $ret['log']));
            continue;
        }

        printf("run %s subscriber: %s\n", $script, $ret['log']);
    }

    $db->close();
}

return main($argv);
