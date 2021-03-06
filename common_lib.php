<?php

require_once '/usr/local/lib/php/database.php';
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once 'config.php';
require_once 'modem3g.php';
require_once 'httpio_lib.php';
require_once 'telegram_api.php';
require_once 'boiler_api.php';


function sms_send($type, $recepient, $args = array())
{
    $sms_text = '';

    switch ($type) {
    case 'reboot':
        if (isset($recepient['user_id'])) {
            $user = user_get_by_id($recepient['user_id']);
            $sms_text = sprintf("%s отправил сервер на перезагрузку через %s",
                                $user['name'], $args);
            break;
        }
        $sms_text = sprintf("Сервер ушел на перезагрузку по запросу %s", $args);
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
        switch ($args['state']) {
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
                            $args['zone'], $args['action_id']);
        break;

    case 'guard_disable':
        $sms_text = sprintf("Метод: %s, state_id: %s.",
                            $args['method'], $args['state_id']);

        if (isset($args['global_status']))
            $sms_text .= $args['global_status'];
        break;

    case 'guard_enable':
        $sms_text = sprintf("Метод: %s, state_id: %s.",
                            $args['method'], $args['state_id']);

        if (isset($args['global_status']))
            $sms_text .= $args['global_status'];
        break;

    case 'inet_switch':
        $sms_text = sprintf("Интернет переключен на модем %d",
                            $args['modem_num']);
        break;

    default:
        return -EINVAL;
    }

    $modem = new Modem3G(conf_modem()['ip_addr']);

    // creating phones list
    $list_phones = [];
    if (isset($recepient['user_id']) && $recepient['user_id']) {
        $user = user_get_by_id($recepient['user_id']);
        $list_phones = $user['phones'];
    }

    // applying phones list by groups
    if (isset($recepient['groups']))
        foreach ($recepient['groups'] as $group) {
            $group_phones = get_users_phones_by_access_type($group);
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


function telegram_get_admin_chat_id()
{
    $chat = db()->query("SELECT chat_id FROM telegram_chats " .
                        "WHERE type = 'admin'");
    return $chat['chat_id'];
}

function server_reboot($method, $user_id = NULL)
{
    if ($method == "SMS")
        sms_send('reboot',
                 ['user_id' => $user_id,
                  'groups' => ['sms_observer']],
                 $method);

    if ($user_id) {
        $user = user_get_by_id($args['user_id']);
        $text = sprintf("%s отправил сервер на перезагрузку через %s",
                        $user['name'], $method);
    } else
        $text = sprintf("Сервер ушел на перезагрузку по запросу %s", $method);

    telegram_send_msg_admin($text);
    if(DISABLE_HW)
        return;
    run_cmd('halt');
    for(;;);
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

function user_get_by_phone($phone)
{
    $user = db()->query("SELECT * FROM users " .
                        "WHERE phones LIKE \"%" . $phone . "%\" AND enabled = 1");

    if (!$user)
        return NULL;

    $user['phones'] = string_to_array($user['phones']);
    return $user;
}

function user_get_by_id($user_id)
{
    $user = db()->query(sprintf("SELECT * FROM users " .
                                "WHERE id = %d AND enabled = 1", $user_id));

    if (!$user)
        return NULL;

    $user['phones'] = string_to_array($user['phones']);
    return $user;
}

function user_get_by_telegram_id($telegram_user_id)
{
    $user = db()->query(sprintf("SELECT * FROM users " .
                                "WHERE telegram_id = %d AND enabled = 1",
                                $telegram_user_id));

    if (!$user)
        return NULL;

    $user['phones'] = string_to_array($user['phones']);
    return $user;
}


function get_users_phones_by_access_type($type)
{
    $users = db()->query_list(sprintf('SELECT * FROM users '.
                                      'WHERE %s = 1 AND enabled = 1', $type));
    $list_phones = array();
    foreach ($users as $user)
        $list_phones[] = string_to_array($user['phones'])[0];

    return $list_phones;
}

function get_all_users_phones_by_access_type($type)
{
    $users = db()->query_list(sprintf('SELECT * FROM users '.
                                      'WHERE %s = 1 AND enabled = 1', $type));
    $list_phones = array();
    foreach ($users as $user) {
        $phones = string_to_array($user['phones']);
        foreach ($phones as $phone)
            $list_phones[] = $phone;
    }

    return $list_phones;
}


function get_global_status()
{
    $modem = new Modem3G(conf_modem()['ip_addr']);

    $guard_stat = get_guard_state();
    $balance = $modem->get_sim_balanse();
    $modem_stat = $modem->get_status();
    $lighting_stat = get_street_light_stat();
    $padlocks_stat = get_padlocks_stat();
    $termosensors = get_termosensors_stat();

    $ret = run_cmd('uptime');
    preg_match('/up (.+),/U', $ret['log'], $mathes);
    $uptime = $mathes[1];

    return ['guard_stat' => $guard_stat,
            'balance' => $balance,
            'modem_stat' => $modem_stat,
            'uptime' => $uptime,
            'lighting_stat' => $lighting_stat,
            'padlocks_stat' => $padlocks_stat,
            'termo_sensors' => $termosensors,
            'battery' => get_battery_info(),
            'power_states' => get_power_states(),
            'ups_state' => get_ups_state(),
            'boiler_state' => boiler_stat(),
    ];
}


function format_global_status_for_sms($stat)
{
    $text = '';
    if (isset($stat['guard_stat'])) {
        $text_who = '';
        switch ($stat['guard_stat']['state']) {
        case 'sleep':
            $mode = "откл.";
            break;

        case 'ready':
            $mode = "вкл.";
            break;
        }
        $text .= sprintf("Охрана: %s, ", $mode);

        if (count($stat['guard_stat']['ignore_zones'])) {
            $text .= sprintf("Игнор: ");
            foreach ($stat['guard_stat']['ignore_zones'] as $zone_id) {
                $zone = zone_get_by_io_id($zone_id);
                $text .= sprintf("%s, ", $zone['name']);
            }
            $text .= '.';
        }

        if (count($stat['guard_stat']['blocking_zones'])) {
            $text .= sprintf("Заблокир: ");
            foreach ($stat['guard_stat']['blocking_zones'] as $zone) {
                $text .= sprintf("%s, ", $zone['name']);
            }
            $text .= '.';
        }

        if (isset($stat['guard_stat']['user_name']) && $mode)
            $text .= sprintf("%s: %s, ", $mode,
                             $stat['guard_stat']['user_name']);
    }

    if (isset($stat['lighting_stat'])) {
        foreach ($stat['lighting_stat'] as $row) {
            switch ($row['state']) {
            case 0:
                $mode = "откл.";
                break;

            case 1:
                $mode = "вкл.";
                break;
            }
            $text .= sprintf("Освещение '%s': %s, ", $row['name'], $mode);
        }
    }
    if (isset($stat['padlocks_stat'])) {
        foreach ($stat['padlocks_stat'] as $row) {
            switch ($row['state']) {
            case 0:
                $mode = "закр.";
                break;

            case 1:
                $mode = "откр.";
                break;
            }
            $text .= sprintf("Замок '%s': %s, ", $row['name'], $mode);
        }
    }

    if (isset($stat['uptime'])) {
        $text .= sprintf("Uptime: %s, ", $stat['uptime']);
    }

    if (isset($stat['balance'])) {
        $text .= sprintf("Баланс: %s, ", $stat['balance']);
    }

    if (isset($stat['battery'])) {
        if (!is_array($stat['battery']))
            $text .= sprintf("ошибка АКБ, ");
        else
            $text .= sprintf("АКБ: %.2fv,%.2fA, ",
                             $stat['battery']['voltage'],
                             $stat['battery']['current']);
    }

    if (isset($stat['power_states'])) {
        $text .= sprintf("Внешн. пит:%d, пит.ИБП:%d, ",
                         $stat['power_states']['input'],
                         $stat['power_states']['ups']);
    }

    if (isset($stat['ups_state'])) {
        $text .= sprintf("250VDC:%d, 14VDC:%d, ups_stat:%s ",
                         $stat['ups_state']['vdc_out_state'],
                         $stat['ups_state']['standby_state'],
                         $stat['ups_state']['charger_state']);
    }

    return $text;
}


function format_global_status_for_telegram($stat)
{
    $text = '';
    if (isset($stat['guard_stat'])) {
        $text_who = '';
        switch ($stat['guard_stat']['state']) {
        case 'sleep':
            $mode = "отключена";
            $text_who = "Отключил охрану";
            break;

        case 'ready':
            $mode = "включена";
            $text_who = "Включил охрану";
            break;
        }
        $text .= sprintf("Охрана: %s\n", $mode);

        if (count($stat['guard_stat']['ignore_zones'])) {
            $text .= sprintf("Игнорированные зоны:\n");
            foreach ($stat['guard_stat']['ignore_zones'] as $zone_id) {
                $zone = zone_get_by_io_id($zone_id);
                $text .= sprintf("               %s\n", $zone['name']);
            }
        }

        if (count($stat['guard_stat']['blocking_zones'])) {
            $text .= sprintf("Заблокированные зоны: ");
            foreach ($stat['guard_stat']['blocking_zones'] as $zone)
                $text .= sprintf("               %s\n", $zone['name']);
        }

        if (isset($stat['guard_stat']['user_name']) && $text_who)
            $text .= sprintf("%s: %s через %s в %s\n", $text_who,
                             $stat['guard_stat']['user_name'],
                             $stat['guard_stat']['method'],
                             $stat['guard_stat']['created']);
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
            $text .= sprintf("Освещение '%s': %s\n", $row['name'], $mode);
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
            $text .= sprintf("Замок '%s': %s\n", $row['name'], $mode);
        }
    }

    if (isset($stat['uptime'])) {
        $text .= sprintf("Uptime: %s\n", $stat['uptime']);
    }

    if (isset($stat['balance'])) {
        $text .= sprintf("Баланс счета SIM карты: %s\n", $stat['balance']);
    }

    if (isset($stat['termo_sensors'])) {
        foreach($stat['termo_sensors'] as $sensor)
            $text .= sprintf("Температура %s: %.01f градусов\n", $sensor['name'], $sensor['value']);
    }

    if (isset($stat['battery'])) {
        if (!is_array($stat['battery']))
            $text .= sprintf("ошибка АКБ, ");
        else
            $text .= sprintf("АКБ: %.2fv, %.2fA\n",
                             $stat['battery']['voltage'],
                             $stat['battery']['current']);
    }

    if (isset($stat['power_states'])) {
        $text .= sprintf("Питание на вводе: %s\n" .
                         "Питание на ИБП: %s\n" ,
                         $stat['power_states']['input'] ? 'присутствует' : 'отсутствует',
                         $stat['power_states']['ups'] ? 'присутствует' : 'отсутствует');
    }

    if (isset($stat['ups_state'])) {
        $text .= sprintf("Выходное питание ИБП: %s\n" .
                         "Дежурное питание ИБП: %s\n" .
                         "Состояние ИБП: %s\n",
                         $stat['ups_state']['vdc_out_state'] ? 'присутствует' : 'отсутствует',
                         $stat['ups_state']['standby_state'] ? 'присутствует' : 'отсутствует',
                         $stat['ups_state']['charger_state']);
    }

    if (isset($stat['boiler_state'])) {
        $s = $stat['boiler_state'];
        $text .= sprintf("Состояние котла: %s\n" .
                         "Установленная температура котла: %.1f - %.1f градусов\n" .
                         "Установленная температура в мастерской: %.1f градусов\n" .
                         "Текущая температура в мастерской: %.1f градусов\n" .
                         "Средняя температура в мастерской: %.1f градусов\n" .
                         "Средняя температура в радиаторах: %.1f градусов\n" .
                         "Количество запусков котла за текущие сутки: %d\n" .
                         "Время нагрева за текущие сутки: %s\n" .
                         "Объём потраченного топлива за текущие сутки: %.1f л.\n",
                         $s['state'], $s['target_boiler_t_min'], $s['target_boiler_t_max'],
                         $s['target_room_t'], $s['current_room_t'],
                         $s['overage_room_t'], $s['overage_return_water_t'],
                         $s['ignition_counter'], $s['total_burning_time_text'],
                         $s['total_fuel_consumption']);
    }

    return $text;
}


function get_street_light_stat()
{
    $report = [];
    foreach (conf_street_light() as $zone) {
        $zone['state'] = httpio($zone['io'])->relay_get_state($zone['io_port']);
        if ($zone['state'] < 0)
            perror("Can't get relay state %d\n", $zone['io_port']);

        $report[] = $zone;
    }

    return $report;
}

function get_padlocks_stat()
{
    $report = [];
    foreach (conf_padlocks() as $zone) {
        $zone['state'] = httpio($zone['io'])->relay_get_state($zone['io_port']);
        if ($zone['state'] < 0)
            perror("Can't get relay state %d\n", $zone['io_port']);

        $report[] = $zone;
    }

    return $report;
}

define("TEMPERATURES_FILE", "/tmp/temperatures");
define("CURRENT_TEMPERATURES_FILE", "/tmp/current_temperatures");
function get_termosensors_stat()
{
    @$content = file_get_contents(CURRENT_TEMPERATURES_FILE);
    if (!$content)
        return [];

    @$rows = json_decode($content, true);
    if (!$rows || !is_array($rows))
        return [];

    $list = [];
    foreach ($rows as $row) {
        if (!isset(conf_termo_sensors()[$row['sensor_name']]))
            continue;
        $list[] = ['name' => conf_termo_sensors()[$row['sensor_name']],
                   'value' => $row['temperature'],
                   'sensor_name' => $row['sensor_name'],
        ];
    }
    return $list;
}

function get_stored_io_states()
{
    $query = 'SELECT io_output_actions.io_name, ' .
                    'io_output_actions.port, ' .
                    'io_output_actions.state ' .
             'FROM io_output_actions ' .
             'INNER JOIN ' .
                '( SELECT io_name, port, max(id) as last_id ' .
                 'FROM io_output_actions ' .
                 'GROUP BY io_name, port ) as b '.
             'ON io_output_actions.port = b.port AND ' .
                'io_output_actions.io_name = b.io_name AND ' .
                'io_output_actions.id = b.last_id ' .
             'ORDER BY io_output_actions.io_name, io_output_actions.port';

    $rows = db()->query_list($query);
    if (!is_array($rows) || !count($rows))
        return [];

    return $rows;
}


define("UPS_BATT_VOLTAGE_FILE", "/tmp/ups_batt_voltage");
define("UPS_BATT_CURRENT_FILE", "/tmp/ups_batt_current");
define("CHARGER_STAGE_FILE", "/tmp/battery_charge_stage");

function get_battery_info()
{
    @$voltage = trim(file_get_contents(UPS_BATT_VOLTAGE_FILE));
    if ($voltage === FALSE)
        return null;

    @$current = trim(file_get_contents(UPS_BATT_CURRENT_FILE));
    if ($current === FALSE)
        return null;

    return ['voltage' => $voltage,
            'current' => $current];
}


function reboot_sbio($sbio_name)
{
    if (!isset(conf_io()[$sbio_name]))
        return;
    $io_conf = conf_io()[$sbio_name];
    $content = file_get_contents(sprintf("http://%s:%d/reboot",
                                 $io_conf['ip_addr'],
                                 $io_conf['tcp_port']));
    if (!$content)
        return ['status' => 'error',
                'error_msg' => sprintf('Can`t response from %s', $sbio_name)];

    $ret_data = json_decode($content, true);
    if (!$ret_data)
        return ['status' => 'error',
                'error_msg' => sprintf('Can`t decoded response: %s', $content)];

    if ($ret_data['status'] != 'ok')
        return -1;
    return 0;
}

define("HALT_ALL_SYSTEMS_FILE", "/tmp/halt_all_systems");

function halt_all_systems()
{
    if (is_halt_all_systems())
        return;

    if (DISABLE_HW) {
        perror("FAKE: halt all systems, goodbuy. For undo - remove %s\n",
               HALT_ALL_SYSTEMS_FILE);
        file_put_contents(HALT_ALL_SYSTEMS_FILE, 1);
        return;
    }
    run_cmd("halt");
}

function is_halt_all_systems()
{
    return @file_get_contents(HALT_ALL_SYSTEMS_FILE);
}

function get_power_states()
{
    $power = [];
    $external_input_power_port = httpio_port(conf_ups()['external_input_power_port']);
    $external_ups_power_port = httpio_port(conf_ups()['external_ups_power_port']);
    $input_state = $external_input_power_port->get();
    $ups_state = $external_ups_power_port->get();

    if ($input_state >= 0)
        $power['input'] = $input_state;

    if ($ups_state >= 0)
        $power['ups'] = $ups_state;
    return $power;
}

function get_ups_state()
{
    $stat = [];
    $vdc_out_check_port = httpio_port(conf_ups()['vdc_out_check_port']);
    $standby_check_port = httpio_port(conf_ups()['standby_check_port']);

    $stat['vdc_out_state'] = $vdc_out_check_port->get();
    $stat['standby_state'] = $standby_check_port->get();
    @$stat['charger_state'] = file_get_contents(CHARGER_STAGE_FILE);
    return $stat;
}


/**
 * Get duration between UPS power loss and UPS power resume
 */
function get_last_ups_duration()
{
    $last_ext_power_state = db()->query("SELECT UNIX_TIMESTAMP(created) as created, state " .
                                        "FROM ext_power_log WHERE type='ups' " .
                                        "ORDER BY id DESC LIMIT 1");
    if (!is_array($last_ext_power_state) ||
        $last_ext_power_state['state'] == 1)
        return NULL;

    return time() - $last_ext_power_state['created'];
}

