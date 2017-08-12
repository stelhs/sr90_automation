<?php
require_once 'config.php';

class Telegram_api {
    
    function post_request($method_name)
    {
        $full_url = spintf('https://api.telegram.org/bot%s/', 
                                    conf_telegram_bot()['token'], $method_name);

        $query = '';

        $options = array(
            'http' => array(
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => $query,
            )
        );
        $context = stream_context_create($options);
        @$result = file_get_contents($full_url, false, $context);
        if ($result == FALSE)
            return -EPARSE;

        dump($result);
    }
}