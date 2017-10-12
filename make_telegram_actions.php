#!/usr/bin/php
<?php 
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';
require_once 'telegram_api.php';
require_once 'config.php';

define("TELEGRAM_ACTIONS_DIR", "telegram_actions/");
define("MSG_LOG_LEVEL", LOG_NOTICE);


$commands = [
                ['cmd' => ['включи охрану', 'guard on'],
                 'script' => 'guard_commands.php', 'args' => 'on'],

                ['cmd' => ['отключи охрану', 'guard off'],
                 'script' => 'guard_commands.php', 'args' => 'off'],

                ['cmd' => ['отключи охрану замки не открывай'],
                 'script' => 'guard_commands.php', 'args' => 'off lock', 'wr' => 1],

                ['cmd' => ['открой замок кунга'],
                 'script' => 'padlock_commands.php', 'args' => 'open 1'],

                ['cmd' => ['закрой замок кунга'],
                 'script' => 'padlock_commands.php', 'args' => 'close 1', 'wr' => 1],

                ['cmd' => ['открой замок красного контейнера'],
                 'script' => 'padlock_commands.php', 'args' => 'open 2'],

                ['cmd' => ['закрой замок красного контейнера'],
                 'script' => 'padlock_commands.php', 'args' => 'close 2', 'wr' => 1],

                ['cmd' => ['открой замок синего контейнера'],
                 'script' => 'padlock_commands.php', 'args' => 'open 3'],

                ['cmd' => ['закрой замок синего контейнера'],
                 'script' => 'padlock_commands.php', 'args' => 'close 3', 'wr' => 1],

                ['cmd' => ['закрой все замки'],
                 'script' => 'padlock_commands.php', 'args' => 'close all', 'wr' => 1],

                ['cmd' => ['включи уличный свет', 'light on'],
                 'script' => 'light_commands.php', 'args' => 'on'],

                ['cmd' => ['отключи уличный свет', 'light off'],
                 'script' => 'light_commands.php', 'args' => 'off', 'wr' => 1],

                ['cmd' => ['перезагрузись', 'reboot'],
                 'script' => 'reboot_cmd.php', 'wr' => 1],

                ['cmd' => ['статус', 'stat'],
                 'script' => 'stat_cmd.php'],
            ];

function mk_help()
{
    global $commands;

    foreach($commands as $row) {
        $msg .= sprintf("   - 'skynet %s'\n", $row['cmd'][0]);
        if (isset($row['wr']))
            for ($i = 0; $i < $row['wr']; $i++)
                $msg .= "\n";
    }

    return $msg;
}

function main($argv) {
    global $commands;
    
    $rc = 0;
    if (count($argv) < 4)
        return -EINVAL;

    $from_user_id = strtolower(trim($argv[1]));
    $chat_id = strtolower(trim($argv[2]));
    $msg_text = strtolower(trim($argv[3]));
    $msg_id = isset($argv[4]) ? strtolower(trim($argv[4])) : 0;
    
    $db = new Database;
    $rc = $db->connect(conf_db());
    if ($rc) {
        msg_log(LOG_ERR, "can't connect to database");
        return -EBASE;
    }

    $telegram = new Telegram_api($db);

    $words = split_string($msg_text);
    if (!$words)
        return -EINVAL;

    $arg1 = mb_strtolower($words[0], 'utf8');
    $arg2 = mb_strtolower($words[1], 'utf8');
    if ($arg1 != "skynet" && $arg1 != "скайнет")
        return -EINVAL;

    if ($arg2 == "голос") {
        $telegram->send_message($chat_id, "Слушаю вас внимательно\n", $msg_id);
        return 0;
    }

    if ($arg2 == "команды" || $arg2 == "что умеешь?" || $arg2 == "help") {
        $telegram->send_message($chat_id, "Я умею следующее:\n\n" . mk_help(), $msg_id);
        return 0;
    }
    
    $query = mb_strtolower(array_to_string($words, ' '), 'utf8');
    printf("query = %s\n", $query);

    $script = NULL;
    $args = '';
    foreach ($commands as $row)
        foreach ($row['cmd'] as $cmd) {
            $p = strpos($query, $cmd);
            if ($p === FALSE)
                continue;

            $script = $row['script'];
            $args = $row['args'] . substr($query, $p + strlen($cmd));
            break;
       }
    printf("script = %s\n", $script);
    printf("args = %s\n", $args);

    if (!$script) {
        if (!$arg2)
            $telegram->send_message($chat_id, "Что сделать?\n\nВозможные варианты:\n" . mk_help(), $msg_id);
        else {
            $telegram->send_message($chat_id, "Не поняла\n\n", $msg_id);
            msg_log(LOG_ERR, "can't recognize query\n");
        }
        return -EINVAL;
    }

    $cmd = sprintf("%s '%s' '%s' '%s' '%s'", TELEGRAM_ACTIONS_DIR . $script, 
                                  $from_user_id, $chat_id, $msg_id, $args);

    $ret = run_cmd($cmd);
    if ($ret['rc']) {
        msg_log(LOG_ERR, sprintf("script %s: return error: %s\n", 
                                 $script, $ret['log']));
        $telegram->send_message($chat_id, "Ошибка: " . $ret['log'], $msg_id);
        return -EINVAL;
    }

    msg_log(LOG_NOTICE, sprintf("script %s: return:\n%s\n", $script, $ret['log']));
    return 0;
}

return main($argv);
