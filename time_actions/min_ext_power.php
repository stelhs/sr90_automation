#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'common_lib.php';
require_once 'guard_lib.php';

define("EXT_POWER_STATE_FILE", "/run/ext_power_state");

function main($argv) {
    $res = db()->query('SELECT * FROM `ext_power_log` ' .
                       'WHERE id = (SELECT MAX(id) FROM `ext_power_log`) AND ' .
                       'created < (now() - interval 1 minute)');
    if (!isset($res['state']))
        return 0;

    $curr_stat = $res['state'];

    @$prev_stat = file_get_contents(EXT_POWER_STATE_FILE);
    if ($curr_stat == $prev_stat)
        return 0;

    file_put_contents(EXT_POWER_STATE_FILE, $curr_stat);

    $guard_info = get_guard_state();
    if ($guard_info['state'] == 'sleep')
        return 0;

    telegram_send('external_power', ['mode' => $curr_stat]);
   /* sms_send('external_power',
             ['groups' => ['sms_observer']],
             ['mode' => $curr_stat]);*/
    return 0;
}

exit(main($argv));