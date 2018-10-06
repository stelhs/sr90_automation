#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'common_lib.php';
require_once 'guard_lib.php';

define("EXT_POWER_STATE_FILE", "/run/ext_power_state");

function main($argv) {
    $content = file_get_contents(sprintf("http://%s:%d/battery",
                                         conf_io()['sbio1']['ip_addr'],
                                         conf_io()['sbio1']['tcp_port']));
    $ret_data = json_decode($content, true);
    if (!$ret_data)
        return -1;

    if ($ret_data['status'] != 'ok') {
        telegram_send('battery_charger', ['error' => $guard_info['error_msg']]);
        return -1;
    }

    $voltage = $ret_data['voltage'];
    dump($voltage);

    return 0;
}

exit(main($argv));