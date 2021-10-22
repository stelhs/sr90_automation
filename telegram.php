#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';
require_once 'common_lib.php';

require_once 'config.php';
require_once 'telegram_lib.php';

function print_help()
{
    global $argv;
    $utility_name = $argv[0];
    pnotice( "Usage: $utility_name <command> <args>\n" .
             "\tcommands:\n" .
             "\trecv <action_script> - Attempt to receive messages and run <action_script> for each\n" .
             "\t\tExample:\n" .
             "\t\t\t $utility_name recv ./make_telegram_actions.php\n" .

             "\tsend <chat_id> <message_text> - Send message\n" .
             "\t\tExample:\n" .
             "\t\t\t $utility_name send 186579253 'hello world'\n" .

             "\tsend_msg <message_text> - Send message in 'message' chats\n" .
             "\t\tExample:\n" .
             "\t\t\t $utility_name send_msg 'hello world'\n" .

             "\tsend_admin <message_text> - Send message in 'admin' chats\n" .
             "\t\tExample:\n" .
             "\t\t\t $utility_name send_admin 'hello world'\n" .

             "\tsend_admin <message_text> - Send message in 'alarm' chats\n" .
             "\t\tExample:\n" .
             "\t\t\t $utility_name send_alarm 'hello world'\n" .

             "\tlist - List of telegram event handlers\n" .
             "\t\tExample:\n" .
             "\t\t\t $utility_name list\n" .

             "\trun <handler_name> <cmd_name> [text] - Run telegram command\n" .
             "\t\tExample:\n" .
             "\t\t\t $utility_name run common tell привет\n" .

             "\n\n");
}

function main($argv)
{
    $rc = 0;
    if (!isset($argv[1])) {
        print_help();
        return -EINVAL;
    }

    $cmd = strtolower(trim($argv[1]));
    switch ($cmd) {
    case 'recv':
        set_time_limit(90); // set timeout 90 seconds
        $list_msg = telegram()->get_new_messages();

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

            $ret = run_cmd(sprintf("%s '%d' '%s' '%s' '%s' '%s'",
                                   $action_script, $user_id, $msg['chat_id'],
                                   $msg['text'], $msg['msg_id'], $msg['from_id']));
            if ($ret['rc']) {
                perror("script %s: return error: %s\n",
                                         $action_script, $ret['log']);
                continue;
            }

            pnotice("script %s: return:\n%s\n", $action_script, $ret['log']);
        }
        break;

    case 'send':
        if ((!isset($argv[2])) || (!isset($argv[3]))) {
            perror("incorrect params");
            return -EINVAL;
        }

        $chat_id = strtolower(trim($argv[2]));
        $msg = strtolower(trim($argv[3]));

        telegram()->send_message($chat_id, $msg);
        break;

    case 'send_msg':
        if (!isset($argv[2])) {
            perror("incorrect params");
            return -EINVAL;
        }

        $msg = trim($argv[2]);
        tn()->send_to_msg($msg);
        break;

    case 'send_alarm':
        if (!isset($argv[2])) {
            perror("incorrect params");
            return -EINVAL;
        }

        $msg = trim($argv[2]);
        tn()->send_to_alarm($msg);
        break;

    case 'send_admin':
        if (!isset($argv[2])) {
            perror("incorrect params");
            return -EINVAL;
        }

        $msg = trim($argv[2]);
        tn()->send_to_admin($msg);
        break;

    case 'list':
        pnotice("List of handlers:\n");
        foreach (telegram_handlers() as $handler) {
            $class = get_class($handler);
            $info = new ReflectionClass($class);
            pnotice("\t%s : %s +%d\n", $handler->name(),
                $info->getFileName(), $info->getStartLine());
            foreach ($handler->cmd_list() as $cmd)
                pnotice("\t\t%s - %s\n", $cmd['method'], $cmd['cmd'][0]);
            pnotice("\n");
        }
        break;

    case 'run':
        if ((!isset($argv[2])) || (!isset($argv[3]))) {
            perror("Not enought params\n");
            return -EINVAL;
        }
        $hname = $argv[2];
        $method = $argv[3];

        $handler = NULL;
        foreach (telegram_handlers() as $h)
            if ($h->name() == $hname) {
                $handler = $h;
                break;
            }

        if (!$handler) {
            perror("Handler '%s' has not found\n", $hname);
            return -EINVAL;
        }

        $found = false;
        foreach ($handler->cmd_list() as $cmd)
            if ($cmd['method'] == $method)
                $found = true;

        if (!$found) {
            perror("Method '%s' has not found\n", $method);
            return -EINVAL;
        }

        $text = NULL;
        if (isset($argv[4]))
            $text = $argv[4];

        pnotice("run handler %s:%s\n", $hname, $method);
        $handler->$method(tg_admin_chat_id(), 0, 0, NULL, $text);
        break;

    default:
        perror("incorrect command");
        $rc = -EINVAL;
    }

    return $rc;
}

exit(main($argv));

