<?php

require_once 'config.php';
require_once 'modem3g.php';

function serv_ctrl_send_sms($type, $phones_list, $args)
{
    switch ($type) {
    case 'reboot_sms':
        $sms_text = sprintf("Сервер ушел на перезагрузку по запросу SMS");
        break;
        
    case 'status':
        $sms_text = $args;
        break;
        
    case 'lighting_on':
        $sms_text = sprintf("Освещение участка включено.");
        break;

    case 'lighting_off':
        $sms_text = sprintf("Освещение участка отключено.");
        break;

    default: 
        return -EINVAL;
    }
    
    $modem = new Modem3G(conf_modem()['ip_addr']);
    
    foreach ($phones_list as $phone) {
        $ret = $modem->send_sms($phone, $sms_text);
        if ($ret) {
            msg_log(LOG_ERR, "Can't send SMS: " . $ret);
            return -EBUSY;
        }
    }
}

function get_day_night()
{
    $sun_info = date_sun_info(time(), 54.014634, 28.013484);
    $curr_time = time();

    if ($curr_time > $sun_info['nautical_twilight_begin'] && 
        $curr_time < $sun_info['nautical_twilight_end'])
            return 'day';

    return 'night';
}

function user_get_by_phone($db, $phone)
{
    $user = $db->query("SELECT * FROM users " .
                      "WHERE phones LIKE \"%" . $phone . "%\" AND enabled = 1");
    
    $user['phones'] = string_to_array($user['phones']);
    return $user;
}

function user_get_by_id($db, $user_id)
{
    $user = $db->query(sprintf("SELECT * FROM users " .
                              "WHERE id = %d", $user_id));

    $user['phones'] = string_to_array($user['phones']);
    return $user;
}


function get_users_phones_by_access_type($db, $type)
{
    $users = $db->query_list(sprintf('SELECT * FROM users '.
                             'WHERE %s = 1 AND enabled = 1', $type));
    $list_phones = array();
    foreach ($users as $user)
        $list_phones[] = string_to_array($user['phones'])[0];
        
    return $list_phones;
}

function get_all_users_phones_by_access_type($db, $type)
{
    $users = $db->query_list(sprintf('SELECT * FROM users '.
                             'WHERE %s = 1 AND enabled = 1', $type));
    $list_phones = array();
    foreach ($users as $user) {
        $phones = string_to_array($user['phones']);
        foreach ($phones as $phone)
            $list_phones[] = $phone;
    }
        
    return $list_phones;
}


function get_global_status($db)
{
    $modem = new Modem3G(conf_modem()['ip_addr']);
    $mio = new Mod_io($db);
    
    $guard_state = get_guard_state($db);
    $balance = $modem->get_sim_balanse();
    $modem_stat = $modem->get_global_status();
    $lighting = $mio->relay_get_state(conf_guard()['lamp_io_port']);
        
    $ret = run_cmd('uptime');
    preg_match('/up (.+),/U', $ret['log'], $mathes);
    $uptime = $mathes[1];

    return array('guard_state' => $guard_state['state'],
                  'balance' => $balance,
                  'radio_signal_level' => $modem_stat['signal_strength'],
                  'uptime' => $uptime,
                  'lighting' => $lighting,
                );
}


function get_formatted_global_status($db)
{
    $stat = get_global_status($db);
    switch ($stat['guard_state']) {
    case 'sleep':
        $stat['guard_state'] = "отключена";
        break;

    case 'ready':
        $stat['guard_state'] = "включена";
        break;
    }

    switch ($stat['lighting']) {
    case 0:
        $stat['lighting'] = "отключено";
        break;

    case 1:
        $stat['lighting'] = "включено";
        break;
    }

    return sprintf("Охрана: %s, Баланс счета: %s, Уровень сигнала: %s, uptime: %s, Освещение: %s.", 
                   $stat['guard_state'],
                   $stat['balance'],
                   $stat['radio_signal_level'],
                   $stat['uptime'],
                   $stat['lighting']);
}
