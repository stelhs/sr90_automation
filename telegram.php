#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';
require_once 'server_control_lib.php';

require_once 'config.php';
require_once 'telegram_api.php';
$utility_name = $argv[0];
define("MSG_LOG_LEVEL", LOG_NOTICE);

function print_help()
{
    global $utility_name;
    echo "Usage: $utility_name <command> <args>\n" . 
             "\tcommands:\n" .
             "\tmsg_recv <action_script> - Attempt to receive messages and run <action_script> for each\n" .
             "\t\tExample:\n" .
             "\t\t\t $utility_name msg_recv ./make_telegram_actions.php\n" .
             "\tmsg_send <chat_id> <message_text> - Send message\n" .
             "\t\tExample:\n" .
             "\t\t\t $utility_name msg_send 186579253 'hello world'\n" .
    "\n\n";
}

function main($argv)
{
    $rc = 0;
    if (!isset($argv[1]))
        return -EINVAL;

    $db = new Database;
    $rc = $db->connect(conf_db());
    if ($rc) {
        msg_log(LOG_ERR, "can't connect to database");
        return -EBASE;
    }

    $telegram = new Telegram_api($db);

    $cmd = strtolower(trim($argv[1]));
    switch ($cmd) {
    case 'msg_recv':
        $list_msg = $telegram->get_new_messages();

        if (!is_array($list_msg))
            return 0;

        if (!count($list_msg))
            return 0;

        $action_script = "";
        if (isset($argv[2]))
            $action_script = $argv[2];

        foreach ($list_msg as $msg) {
            printf("received from %s: %s\n", $msg['from_name'], $msg['text']);
            if (!$action_script)
                continue;

            $user = user_get_by_telegram_id($db, $msg['from_id']);
            $user_id = 0;
            if (is_array($user))
                $user_id = $user['id'];

            $ret = run_cmd(sprintf("%s '%d' '%s' '%s' '%s'", 
                                   $action_script, $user_id, $msg['chat_id'],
                                   $msg['text'], $msg['msg_id']));
            if ($ret['rc']) {
                msg_log(LOG_ERR, sprintf("script %s: return error: %s\n", 
                                         $action_script, $ret['log']));
                continue;
            }

            msg_log(LOG_NOTICE, sprintf("script %s: return:\n%s\n", 
                                    $action_script, $ret['log']));
        }
        break;

    case 'msg_send':
        if ((!isset($argv[2])) || (!isset($argv[3]))) {
            msg_log(LOG_ERR, "incorrect params");
            return -EINVAL;
        }

        $chat_id = strtolower(trim($argv[2]));
        $msg = strtolower(trim($argv[3]));

        $telegram->send_message($chat_id, $msg);
        break;

    default:
        msg_log(LOG_ERR, "incorrect command");
        $rc = -EINVAL;
    }

    return $rc;
}

$rc = main($argv);
if ($rc) {
    print_help();
}
