<?php
require_once '/usr/local/lib/php/common.php';

require_once 'config.php';
require_once 'guard_api.php';
require_once 'gates_api.php';
require_once 'boiler_api.php';
require_once 'modem3g.php';
require_once 'padlock_api.php';
require_once 'power_api.php';
require_once 'common_lib.php';

class Telegram_api {
    function __construct()
    {
        $this->log = new Plog('sr90:Telegram');
        $this->last_rx_update_id = 0;
    }

    function post_request($method_name, $params = [])
    {
        $full_url = sprintf('https://api.telegram.org/bot%s/%s',
                                    conf_telegram_bot()['token'], $method_name);

        $query = http_build_query($params);

        $options = array(
            'http' => array(
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => $query,
                'timeout' => 45,
            )
        );
        $context = stream_context_create($options);
        @$result = file_get_contents($full_url, false, $context);
        if ($result == FALSE) {
            $this->log->err("post_request(): Can't make POST request");
            return -EPARSE;
        }

        return json_decode($result, true);
    }

    function recv_messages($from_update_id)
    {
        $resp = $this->post_request('getUpdates', ['offset' => $from_update_id + 1,
                                                   'limit' => 10,
                                                   'timeout' => 30]);
        if (!is_array($resp)) {
            $this->log->err("recv_messages(): Can't make POST request %s\n", $resp);
            return NULL;
        }

        if ($resp['ok'] != 1) {
            $this->log->err("recv_messages(): error POST request, ok=%s", $resp['ok']);
            return NULL;
        }

        if (!count($resp['result']))
            return NULL;

        $list_msg = [];
        foreach ($resp['result'] as $row) {
            if (!isset($row['update_id']))
                continue;

            if (!isset($row['message']))
                continue;

            if (!isset($row['message']['text']))
                continue;

            $msg = ['update_id' =>   $row['update_id'],
                    'msg_id' =>      $row['message']['message_id'],
                    'date' =>        $row['message']['date'],
                    'from_name' =>   $row['message']['from']['first_name'],
                    'from_id' =>     $row['message']['from']['id'],
                    'chat_id' =>     $row['message']['chat']['id'],
                    'chat_type' =>   $row['message']['chat']['type'],
                    'chat_name' =>   ($row['message']['chat']['type'] == 'private' ?
                                              $row['message']['chat']['first_name'] :
                                              $row['message']['chat']['title']),
                    'text'      =>   $row['message']['text'],
            ];

            $list_msg[] = $msg;
        }

        if (!count($list_msg))
            return NULL;

        return $list_msg;
    }

    function send_message($chat_id, $text,
                $reply_to_message_id = 0, $disable_notification = false)
    {
        $params = ['chat_id' => $chat_id, 'text' => $text];
        if ($reply_to_message_id)
            $params['reply_to_message_id'] = $reply_to_message_id;
        if ($disable_notification)
            $params['disable_notification'] = $disable_notification;

        $resp = $this->post_request('sendMessage', $params);
        if (!is_array($resp)) {
            $this->log->err("send_message(): Can't make POST request for send %s\n", $resp);
            return $resp;
        }

        if ($resp['ok'] != 1) {
            $this->log->err("send_message(): error POST request for send, ok=%s\n", $resp['ok']);
            return -EBUSY;
        }

        return 0;
    }
}

function telegram()
{
    static $telegram = NULL;
    if ($telegram)
        return $telegram;

    $telegram = new Telegram_api();
    return $telegram;
}

class Telegram_notifier {
    function __construct()
    {
        $this->log = new Plog('sr90:telegram_notifier');
        $this->last_rx_update_id = NULL;
        $this->last_rx_update_file = getenv("HOME") . '/.telegram_last_rx_update_id';
    }

    function chats($type = NULL)
    {
        $query = "SELECT * FROM telegram_chats WHERE enabled = 1 ";
        $query .= $type ? sprintf("AND type = '%s'", $type) : '';
        $list = db()->query_list($query);
        return $list;
    }

    function send_to_admin()
    {
        $argv = func_get_args();
        $format = array_shift($argv);
        $msg = vsprintf($format, $argv);

        $chat_list = $this->chats('admin');
        foreach ($chat_list as $chat)
            telegram()->send_message($chat['chat_id'], $msg);
    }

    function send_to_alarm()
    {
        $argv = func_get_args();
        $format = array_shift($argv);
        $msg = vsprintf($format, $argv);

        $chat_list = $this->chats('alarm');
        foreach ($chat_list as $chat)
            telegram()->send_message($chat['chat_id'], $msg);
    }

    function send_to_msg()
    {
        $argv = func_get_args();
        $format = array_shift($argv);
        $msg = vsprintf($format, $argv);

        $chat_list = $this->chats('messages');
        foreach ($chat_list as $chat)
            telegram()->send_message($chat['chat_id'], $msg);
    }

    function send($chat_id, $msg_id)
    {
        $argv = func_get_args();
        array_shift($argv);
        array_shift($argv);

        $format = array_shift($argv);
        $msg = vsprintf($format, $argv);

        telegram()->send_message($chat_id, $msg, $msg_id);
    }

    function save_last_rx_update_id($rx_update_id)
    {
        $rc = file_put_contents($this->last_rx_update_file, $rx_update_id);
        if (!$rc) {
            $this->log->err("Can't save to %s", $this->last_rx_update_file);
            run_cmd("./periodically.php stop"); // TODO
            $this->send_to_admin("Ошибка записи .telegram_last_rx_update_id, задачи остановлены");
            return;
        }
        $this->last_rx_update_id = $rx_update_id;
    }

    function last_rx_update_id()
    {
        if (!$this->last_rx_update_id)
            @$this->last_rx_update_id = file_get_contents($this->last_rx_update_file);
        return $this->last_rx_update_id;
    }

    function new_messages()
    {
        $list_msg = telegram()->recv_messages($this->last_rx_update_id());
        if (!$list_msg)
            return NULL;

        foreach ($list_msg as $msg)
            db()->insert('telegram_msg', $msg);

        $this->save_last_rx_update_id($msg['update_id']);
        return $list_msg;
    }
}

function tn()
{
    static $tg_notifier = NULL;
    if ($tg_notifier)
        return $tg_notifier;

    $tg_notifier = new Telegram_notifier;
    return $tg_notifier;
}


class Telegram_periodically implements Periodically_events {
    function name()
    {
        return "telegram";
    }

    function interval()
    {
        return 1;
    }

    function match_to_cmd($msg, $cmd_list)
    {

        $msg = strtolower(trim($msg));
        foreach ($cmd_list as $cmd) {
            foreach ($cmd['cmd'] as $cmd_text) {
                if (strstr($msg, $cmd_text)) {
                    $cmd['rt'] = trim(substr($msg, strlen($cmd_text)));
                    return $cmd;
                }
            }
        }
        return NULL;
    }

    function help()
    {
        $msg = '';
        foreach (telegram_handlers() as $handler) {
            foreach ($handler->cmd_list() as $row)
                $msg .= sprintf("    skynet %s\n", $row['cmd'][0]);
            $msg .= "\n";
        }

        return $msg;
    }


    function do_parse($msg, $chat_id, $msg_id)
    {
        $words = string_to_words($msg, " \t:;+-=");
        if (!$words)
            return 0;

        $arg1 = strtolower($words[0]);
        $arg2 = isset($words[1]) ? strtolower($words[1]) : "";
        if ($arg1 != "skynet" && $arg1 != "sky.net" && $arg1 != "скайнет")
            return 0;

        if (strstr($msg, "маразм")) {
            $content = file_get_contents('marazm_response.txt');
            $rows = string_to_rows($content);
            srand(time());
            $rand_key = array_rand($rows, 1);
            tn()->send($chat_id, $msg_id, $rows[$rand_key]);
            return 0;
        }

        if (!$arg2 || $arg2 == "команды" || $arg2 == "что умеешь?" || $arg2 == "help") {
            tn()->send($chat_id, $msg_id,
                        "Слушаю вас внимательно!\n" .
                        "Я умею выполнять следующие команды:\n%s\n\n",
                        $this->help());
            return 0;
        }

        array_shift($words);
        return array_to_string($words, ' ');
    }

    function do() {
        $list_msg = tn()->new_messages();
        if (!$list_msg)
            return;

        foreach ($list_msg as $msg) {
            $text = $msg['text'];
            $text = $this->do_parse($text, $msg['chat_id'], $msg['msg_id']);
            if (!$text)
                continue;

            $user_id = 0;
            $user = user_get_by_telegram_id($msg['from_id']);
            if (is_array($user))
                $user_id = $user['id'];

            foreach (telegram_handlers() as $handler) {
                $cmd = $this->match_to_cmd($text, $handler->cmd_list());
                if (!$cmd)
                    continue;

                $public = false;
                if (isset($cmd['public']) and $cmd['public'] == true)
                    $public = true;

                if ($user_id == 0 && !$public) {
                    tn()->send($msg['chat_id'], $msg['msg_id'],
                        "У вас недостаточно прав чтобы выполнить эту операцию\n");
                    return;
                }

                $arg = NULL;
                if (isset($cmd['arg']))
                    $arg = $cmd['arg'];

                $f = $cmd['method'];
                $handler->$f($msg['chat_id'], $msg['msg_id'], $user_id, $arg, $cmd['rt']);
                return;
            }

            tn()->send($msg['chat_id'], $msg['msg_id'], "Не поняла");
        }
    }
}