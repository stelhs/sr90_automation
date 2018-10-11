#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';
require_once 'common_lib.php';

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
             "\tmsg_send_all <message_text> - Send message in all chats\n" .
             "\t\tExample:\n" .
             "\t\t\t $utility_name msg_send_all 'hello world'\n" .
             "\tmsg_send_admin <message_text> - Send message in admin chat\n" .
             "\t\tExample:\n" .
             "\t\t\t $utility_name msg_send_admin 'hello world'\n" .
             "\n\n";
}

function main($argv)
{
    $rc = 0;
    if (!isset($argv[1]))
        return -EINVAL;

    $telegram = new Telegram_api();

    $cmd = strtolower(trim($argv[1]));
    switch ($cmd) {
    case 'msg_recv':
        set_time_limit(90); // set timeout 90 seconds
        $list_msg = $telegram->get_new_messages();

        if (!is_array($list_msg))
            return 0;

        if (!count($list_msg))
            return 0;

        $action_script = "";
        if (isset($argv[2]))
            $action_script = $argv[2];

        foreach ($list_msg as $msg) {
            perror("received from %s: %s\n", $msg['from_name'], $msg['text']);
            if (!$action_script)
                continue;

            $user = user_get_by_telegram_id($msg['from_id']);
            $user_id = 0;
            if (is_array($user))
                $user_id = $user['id'];

            if ($user_id == 0)
                perror("unrecognized user ID: %d\n", $msg['from_id']);

            $ret = run_cmd(sprintf("%s '%d' '%s' '%s' '%s'",
                                   $action_script, $user_id, $msg['chat_id'],
                                   $msg['text'], $msg['msg_id']));
            if ($ret['rc']) {
                perror("script %s: return error: %s\n",
                                         $action_script, $ret['log']);
                continue;
            }

            pnotice("script %s: return:\n%s\n", $action_script, $ret['log']);
        }
        break;

    case 'msg_send':
        if ((!isset($argv[2])) || (!isset($argv[3]))) {
            perror("incorrect params");
            return -EINVAL;
        }

        $chat_id = strtolower(trim($argv[2]));
        $msg = strtolower(trim($argv[3]));

        $telegram->send_message($chat_id, $msg);
        break;

    case 'msg_send_all':
        if (!isset($argv[2])) {
            perror("incorrect params");
            return -EINVAL;
        }

        $msg = trim($argv[2]);
        $chat_list = db()->query_list("SELECT * FROM telegram_chats WHERE enabled = 1");
        foreach ($chat_list as $row)
            $telegram->send_message($row['chat_id'], $msg);

        break;

    case 'msg_send_admin':
        if (!isset($argv[2])) {
            perror("incorrect params");
            return -EINVAL;
        }

        $msg = trim($argv[2]);
        $chat_id = telegram_get_admin_chat_id();
        $telegram->send_message($chat_id, $msg);
        break;


    default:
        perror("incorrect command");
        $rc = -EINVAL;
    }

    return $rc;
}

$rc = main($argv);
if ($rc) {
    print_help();
    exit($rc);
}
