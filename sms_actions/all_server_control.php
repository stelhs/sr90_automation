#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/database.php';
require_once 'config.php';
require_once 'guard_lib.php';
require_once 'modem3g.php';
require_once 'server_control_lib.php';

$utility_name = $argv[0];

function main($argv) {
    if (count($argv) < 4) {
        printf("a few scripts parameters\n");
        return -EINVAL;
    }

    $db = new Database;
    $rc = $db->connect(conf_db());
    if ($rc) {
        printf("can't connect to database");
        return -EBASE;
    }

    $sms_date = trim($argv[1]);
    $phone = trim($argv[2]);
    $sms_text = trim($argv[3]);

    $user = user_get_by_phone($db, $phone);
    if (!$user || !$user['serv_control'])
        return -EINVAL;

    // get list phones for SMS subscribers
    $users = $db->query_list('SELECT * FROM users '.
                             'WHERE serv_control = 1');
    $list_phones = array();
    foreach ($users as $user)
        $list_phones[] = string_to_array($user['phones'])[0];

    $cmd = parse_sms_command($sms_text);

    switch (strtolower($cmd['cmd'])) {
    case 'reboot':
        serv_ctrl_send_sms('reboot_sms', $list_phones);
        run_cmd('halt');
        break;    

    case 'stat':
        $modem = new Modem3G(conf_modem()['ip_addr']);

        $guard_state = get_guard_state($db);
        $balance = $modem->get_sim_balanse();
        $modem_stat = $modem->get_global_status();

        $ret = run_cmd('uptime');
        preg_match('/up (.+),/U', $ret['log'], $mathes);
        $uptime = $mathes[1];

        $stat = array('guard_state' => $guard_state['state'],
                      'balance' => $balance,
                      'radio_signal_level' => $modem_stat['signal_strength'],
                      'uptime' => $uptime,
        );

        serv_ctrl_send_sms('status', $list_phones, $stat);
        break;

    default:
        return -EINVAL;
    }    

    return 0;
}


return main($argv);