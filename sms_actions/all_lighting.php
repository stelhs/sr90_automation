#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/database.php';
require_once 'config.php';
require_once 'guard_lib.php';
require_once 'modem3g.php';
require_once 'mod_io_lib.php';
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
    $mio = new Mod_io($db);

    $sms_date = trim($argv[1]);
    $phone = trim($argv[2]);
    $sms_text = trim($argv[3]);

    $user = user_get_by_phone($db, $phone);
    if (!$user || !$user['serv_control'])
        return -EINVAL;

    $list_phones = get_users_phones_by_access_type('sms_observer');
    if ($user['phones'][0] && !in_array($user['phones'][0], $list_phones))
        $list_phones[] = $user['phones'][0];

    $cmd = parse_sms_command($sms_text);

    switch (strtolower($cmd['cmd'])) {
    case 'light':
        if (!isset($cmd['args'][0]))
            return -EINVAL;

        $mode = $cmd['args'][0];
        switch ($mode) {
        case "on":
            $mio->relay_set_state(conf_guard()['lamp_io_port'], 1);
            serv_ctrl_send_sms('lighting_on', $list_phones);
            break;

        case "off":
            $mio->relay_set_state(conf_guard()['lamp_io_port'], 0);
            serv_ctrl_send_sms('lighting_off', $list_phones);
            break;
        break;
        }

    default:
        return -EINVAL;
    }    

    return 0;
}

return main($argv);