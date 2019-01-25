#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'common_lib.php';
require_once 'guard_lib.php';
require_once 'telegram_api.php';

function main($argv) {
    $coins = ['ETH'];
    foreach ($coins as $coin) {
        $filename = sprintf(".crypto_currency_%s_threshold", strtolower($coin));
        @$threshold = (float)(file_get_contents($filename));
        if (!$threshold)
            continue;

        @$info = json_decode(file_get_contents(
                             sprintf("https://api.binance.com/api/v3/ticker/price?symbol=%sUSDT", $coin)), true);
        if (!is_array($info))
            continue;

        if ($info['price'] < $threshold)
            continue;

        $msg = sprintf("Цена на %s %f USDT", $coin, $info['price']);
        telegram_send_msg_admin($msg);
        file_put_contents($filename, "");
    }

    return 0;
}

exit(main($argv));