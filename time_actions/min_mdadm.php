#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/database.php';
require_once 'config.php';
require_once 'server_control_lib.php';

define("MDSTAT_FILE", "/tmp/mdstat_mode");

function main($argv) {
    @$prev_mode = file_get_contents(MDSTAT_FILE);
    $curr_stat = get_mdstat();
    if ($curr_stat['mode'] == $prev_mode)
        return 0;

    file_put_contents(MDSTAT_FILE, $curr_stat['mode']);

    $db = new Database;
    $rc = $db->connect(conf_db());
    if ($rc) {
        printf("can't connect to database");
        return -EBASE;
    }

    $list_phones = get_users_phones_by_access_type($db, 'serv_control');
    serv_ctrl_send_sms('mdadm', $list_phones, $curr_stat);
    return 0;
}

return main($argv);