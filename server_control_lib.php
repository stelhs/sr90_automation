<?php

require_once '/usr/local/lib/php/database.php';
require_once 'config.php';
require_once 'modem3g.php';
require_once 'mod_io_lib.php';


function sms_send($type, $recepient, $args = array())
{
    $db = new Database;
    $rc = $db->connect(conf_db());
    if ($rc) {
        printf("can't connect to database");
        return -EBASE;
    }

    switch ($type) {
    case 'reboot_sms':
        $sms_text = sprintf("Сервер ушел на перезагрузку по запросу SMS");
        break;

    case 'status':
        $sms_text = $args;
        break;
        
    case 'lighting_on':
        $sms_text = sprintf("Освещение %s включено.", $args['name']);
        break;

    case 'lighting_off':
        $sms_text = sprintf("Освещение %s отключено.", $args['name']);
        break;

    case 'mdadm':
        switch ($args['mode']) {
        case "resync":
            $raid_stat = "синхронизируется " . $args['progress'] . '%';
            break;

        case "recovery":
            $raid_stat = "восстанавливается " . $args['progress'] . '%';
            break;

        case "damage":
            $raid_stat = "поврежден!";
            break;

        case "normal":
            $raid_stat = "восстановлен!";
            break;

        case "no_exist":
        default:
            return;
        }    

        $sms_text = sprintf("RAID1: %s", $raid_stat);
        break;

    case 'external_power':
        switch ($args['mode']) {
        case "on":
            $sms_text = "Внешнее питание восстановлено";
            break;

        case "off":
            $sms_text = "Отключено внешнее питание";
            break;
        }
        break;

    case 'alarm':
        $sms_text = sprintf("Внимание!\nСработала %s, событие: %d", 
                                $args['sensor'], $args['action_id']);
        break;

    case 'guard_disable':
        $sms_text = sprintf("Охрана отключена. Метод: %s, state_id: %s.",
                            $args['method'], $args['state_id']);

        if (isset($args['user_name']) && $args['user_name'])
            $sms_text .= sprintf(" Отключил: %s.", $args['user_name']);

        if (isset($args['global_status']))
            $sms_text .= $args['global_status'];
        break;

    case 'guard_enable':
        $sms_text = sprintf("Охрана включена. Метод: %s, state_id: %s.",
                            $args['method'], $args['state_id']);

        if (isset($args['user_name']) && $args['user_name'])
            $sms_text .= sprintf(" Включил: %s.", $args['user_name']);

        if (count($args['ignore_sensors'])) {
            $sms_text .= sprintf(" Игнор: %s.",
                                 array_to_string($args['ignore_sensors']));
        }
        if (isset($args['global_status']))
            $sms_text .= $args['global_status'];
        break;

    default: 
        return -EINVAL;
    }

    $modem = new Modem3G(conf_modem()['ip_addr']);

    // creating phones list
    $list_phones = [];
    if (isset($recepient['user_id']) && $recepient['user_id']) {
        $user = user_get_by_id($db, $user_id);
        $list_phones = $user['phones']; 
    }

    // applying phones list by groups
    if (isset($recepient['groups']))
        foreach ($recepient['groups'] as $group) {
            $group_phones = get_users_phones_by_access_type($db, $group);
            $list_phones = array_unique(array_merge($list_phones, $group_phones));
        }

    if (!count($list_phones))
        return -EINVAL;
        
    foreach ($list_phones as $phone) {
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
        $curr_time < ($sun_info['nautical_twilight_end'] - 3600))
            return 'day';

    return 'night';
}

function user_get_by_phone($db, $phone)
{
    $user = $db->query("SELECT * FROM users " .
                      "WHERE phones LIKE \"%" . $phone . "%\" AND enabled = 1");

    if (!$user)
        return NULL;

    $user['phones'] = string_to_array($user['phones']);
    return $user;
}

function user_get_by_id($db, $user_id)
{
    $user = $db->query(sprintf("SELECT * FROM users " .
                              "WHERE id = %d", $user_id));

    if (!$user)
        return NULL;

    $user['phones'] = string_to_array($user['phones']);
    return $user;
}

function user_get_by_telegram_id($db, $telegram_user_id)
{
    $user = $db->query(sprintf("SELECT * FROM users " .
                              "WHERE telegram_id = %d", $telegram_user_id));

    if (!$user)
        return NULL;

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
    
    $guard_stat = get_guard_state($db);
    $balance = $modem->get_sim_balanse();
    $modem_stat = $modem->get_status();
    $lighting_stat = get_street_light_stat($db);
    $padlocks_stat = get_padlocks_stat($db);
        
    $ret = run_cmd('uptime');
    preg_match('/up (.+),/U', $ret['log'], $mathes);
    $uptime = $mathes[1];

    $mdstat = get_mdstat();

    return ['guard_stat' => $guard_stat,
            'balance' => $balance,
            'modem_stat' => $modem_stat,
            'uptime' => $uptime,
            'lighting_stat' => $lighting_stat,
            'padlocks_stat' => $padlocks_stat,
            'mdadm' => $mdstat];
}


function format_global_status_for_sms($stat)
{
    $text = '';
    if (isset($stat['guard_stat'])) {
        switch ($stat['guard_stat']['state']) {
        case 'sleep':
            $mode = "отключена";
            break;

        case 'ready':
            $mode = "включена";
            break;
        }
        $text .= sprintf("Охрана: %s, ", $mode); 
    }

    if (isset($stat['lighting_stat'])) {
        foreach ($stat['lighting_stat'] as $row) {
            switch ($row['state']) {
            case 0:
                $mode = "отключено";
                break;

            case 1:
                $mode = "включено";
                break;
            }
            $text .= sprintf("Освещение: %s %s, ", $row['name'], $mode);
        }
    }
    if (isset($stat['padlocks_stat'])) {
        foreach ($stat['padlocks_stat'] as $row) {
            switch ($row['state']) {
            case 0:
                $mode = "закрыт";
                break;

            case 1:
                $mode = "открыт";
                break;
            }
            $text .= sprintf("Замок: %s %s, ", $row['name'], $mode);
        }
    }

    if (isset($stat['mdadm'])) {
        switch ($stat['mdadm']['mode']) {
        case "normal":
            $mode = "исправен";
            break;

        case "no_exist":
            $mode = "отсутсвует";
            break;

        case "resync":
            $mode = "синхронизируется " . $mdstat['progress'] . '%';
            break;

        case "recovery":
            $mode = "восстанавливается " . $mdstat['progress'] . '%';
            break;

        case "damage":
            $mode = "поврежден";
            break;
        }
        $text .= sprintf("RAID1: %s, ", $mode);
    }

    return $text;
}


function format_global_status_for_telegram($stat)
{
    $text = '';
    if (isset($stat['guard_stat'])) {
        switch ($stat['guard_stat']['guard_state']) {
        case 'sleep':
            $mode = "отключена";
            break;

        case 'ready':
            $mode = "включена";
            break;
        }
        $text .= sprintf("Охрана: %s\n", $mode); 
    }

    if (isset($stat['lighting_stat'])) {
        foreach ($stat['lighting_stat'] as $row) {
            switch ($row['state']) {
            case 0:
                $mode = "отключено";
                break;

            case 1:
                $mode = "включено";
                break;
            }
            $text .= sprintf("Освещение: %s %s\n", $row['name'], $mode);
        }
    }

    if (isset($stat['padlocks_stat'])) {
        foreach ($stat['padlocks_stat'] as $row) {
            switch ($row['state']) {
            case 0:
                $mode = "закрыт";
                break;

            case 1:
                $mode = "открыт";
                break;
            }
            $text .= sprintf("Замок: %s %s\n", $row['name'], $mode);
        }
    }

    if (isset($stat['mdadm'])) {
        switch ($stat['mdadm']['mode']) {
        case "normal":
            $mode = "исправен";
            break;

        case "no_exist":
            $mode = "отсутсвует";
            break;

        case "resync":
            $mode = "синхронизируется " . $mdstat['progress'] . '%';
            break;

        case "recovery":
            $mode = "восстанавливается " . $mdstat['progress'] . '%';
            break;

        case "damage":
            $mode = "поврежден";
            break;
        }
        $text .= sprintf("RAID1: %s\n", $mode);
    }

    return $text;
}


function get_mdstat()
{
    $stat = file("/proc/mdstat");

    if (!isset($stat[2]))
        return array('mode' => 'no_exist');

    if (isset($stat[3])) {
        preg_match('/resync[ ]+=[ ]+([0-9\.]+)\%/', $stat[3], $matches);
        if (isset($matches[1]))
            return array('mode' => 'resync',
                         'progress' => $matches[1]);

        preg_match('/recovery[ ]+=[ ]+([0-9\.]+)\%/', $stat[3], $matches);
        if (isset($matches[1]))
            return array('mode' => 'recovery',
                         'progress' => $matches[1]);
    }

    preg_match('/\[[U_]+\]/', $stat[2], $matches);
    $mode = $matches[0];

    if ($mode == '[UU]')
        return array('mode' => 'normal');

    if ($mode == '[_U]' || $mode == '[U_]')
        return array('mode' => 'damage');

    return array('mode' => 'parse_err');
}


function get_street_light_stat($db)
{
    $mio = new Mod_io($db);

    $report = [];
    foreach (conf_street_light() as $zone) {
        $zone['state'] = $mio->relay_get_state($zone['io_port']);
        if ($zone['state'] < 0)
            printf("Can't get relay state %d\n", $zone['io_port']);

        $report[] = $zone; 
    }

    return $report; 
}

function get_padlocks_stat($db)
{
    $mio = new Mod_io($db);

    $report = [];
    foreach (conf_padlocks() as $zone) {
        $zone['state'] = $mio->relay_get_state($zone['io_port']);
        if ($zone['state'] < 0)
            printf("Can't get relay state %d\n", $zone['io_port']);

        $report[] = $zone; 
    }

    return $report;
}
