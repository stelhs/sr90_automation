#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'common_lib.php';

define("MDSTAT_FILE", "/run/mdstat_mode");

function main($argv) {
    @$prev_mode = file_get_contents(MDSTAT_FILE);
    $curr_stat = get_mdstat();
    if ($prev_mode === FALSE) {
        file_put_contents(MDSTAT_FILE, $curr_stat['mode']);
        return 0;
    }

    if ($curr_stat['mode'] == $prev_mode)
        return 0;

    file_put_contents(MDSTAT_FILE, $curr_stat['mode']);

    telegram_send('mdadm', $curr_stat);
//    sms_send('mdadm', ['groups' => ['sms_observer']], $curr_stat);
    return 0;
}

exit(main($argv));
