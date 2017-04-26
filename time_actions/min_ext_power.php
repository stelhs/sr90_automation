#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/database.php';
require_once 'config.php';
require_once 'server_control_lib.php';
require_once 'guard_lib.php';

define("EXT_POWER_STATE_FILE", "/tmp/ext_power_state");

function main($argv) {
    $db = new Database;
    $rc = $db->connect(conf_db());
    if ($rc) {
        printf("can't connect to database");
        return -EBASE;
    }
   
    $res = $db->query('SELECT state FROM ext_power_log ' .
                      'ORDER BY created DESC LIMIT 1');
    $curr_stat = $res['state'];

    @$prev_stat = file_get_contents(EXT_POWER_STATE_FILE);
    if ($curr_stat == $prev_stat)
        return 0;

    file_put_contents(EXT_POWER_STATE_FILE, $curr_stat);

    $guard_info = get_guard_state($db);
    if ($guard_info['state'] == 'sleep')
        return 0;

    $list_phones = get_users_phones_by_access_type($db, 'sms_observer');
    serv_ctrl_send_sms('external_power', $list_phones, array('mode' => $curr_stat));
    return 0;
}

return main($argv);