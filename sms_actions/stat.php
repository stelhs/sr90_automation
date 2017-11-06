#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/database.php';
require_once 'config.php';
require_once 'guard_lib.php';
require_once 'modem3g.php';
require_once 'common_lib.php';

$utility_name = $argv[0];

function main($argv) {
    if (count($argv) < 4) {
        perror("a few scripts parameters\n");
        return -EINVAL;
    }

    $sms_date = trim($argv[1]);
    $user_id = trim($argv[2]);
    $sms_text = trim($argv[3]);

    if (!$user_id)
        return -EINVAL;
    $stat = format_global_status_for_sms(get_global_status());
    sms_send('status',
             ['user_id' => $user_id,
              'groups' => ['sms_observer']], $stat);

    return 0;
}


exit(main($argv));
