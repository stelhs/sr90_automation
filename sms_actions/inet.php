#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'guard_lib.php';
require_once 'modem3g.php';
require_once 'common_lib.php';
require_once 'telegram_api.php';

$utility_name = $argv[0];

function main($argv) {
    if (count($argv) < 4) {
        perror("a few scripts parameters\n");
        return -EINVAL;
    }

    $sms_date = trim($argv[1]);
    $user_id = trim($argv[2]);
    $sms_text = trim($argv[3]);

    $args = strings_to_args($sms_text);
    $mode = $sms_text;
    if (!$mode)
        return -EINVAL;

    switch ($mode) {
    case "1":
        $ret = run_cmd('./inet_switch.sh 1');
        perror("can't change inet route: %s\n", $ret['log']);

        telegram_send_msg_admin("Интернет преключен на модем 1");
        sms_send('inet_switch',
               ['user_id' => $user_id,
                'groups' => ['sms_observer']],
               ['modem_num' => 1]);
        break;

    case "2":
        $ret = run_cmd('./inet_switch.sh 2');
        perror("can't change inet route: %s\n", $ret['log']);

        telegram_send_msg_admin("Интернет преключен на модем 2");
        sms_send('inet_switch',
            ['user_id' => $user_id,
             'groups' => ['sms_observer']],
            ['modem_num' => 2]);
        break;

    default:
        return -EINVAL;
    }

    return 0;
}


return main($argv);
