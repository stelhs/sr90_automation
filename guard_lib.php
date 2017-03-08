<?php

require_once 'config.php';
require_once 'modem3g.php';

function notify_send_by_sms($type, $args)
{
    switch ($type) {
    case 'alarm':
        $sms_text = sprintf("Внимание!\nСработал %s, событие: %d", 
                                $args['sensor'], $args['action_id']);
        break;
    case 'guard_disable':
        $sms_text = sprintf("Охрана отключена. Метод: %s.", $args['method']);
        break;
        
    case 'guard_enable':
        $sms_text = sprintf("Охрана включена. Метод: %s.", $args['method']);
        if (count($args['ignore_sensors'])) {
            $sms_text .= sprintf("Игнор: %s.",
                                 array_to_string($args['ignore_sensors']));
        }
        break;
        
    default: 
        return -EINVAL;
    }
    
    $modem = new Modem3G(conf_modem()['ip_addr']);
    
    foreach (conf_global()['phones'] as $phone) {
        $ret = $modem->send_sms($phone, $sms_text);
        if ($ret) {
            msg_log(LOG_ERR, "Can't send SMS: " . $ret);
            return -EBUSY;
        }
    }
}

function parse_sms_command($text)
{
    $words = split_string($text);
    if (!$words)
        return false;

    $cmd['cmd'] = $words[0];
    unset($words[0]);

    if (!count($words))
        return $cmd;

    foreach ($words as $word)
        $cmd['args'][] = $word;

    return $cmd;
}

function get_sensor_locking_mode($db, $sensor_id)
{
    $data = $db->query("SELECT * FROM blocking_sensors " .
                       "WHERE sense_id = " . $sensor_id . " " .
                       "ORDER by created DESC LIMIT 1");
    return $data ? $data['mode'] : 'unlock';
}

function get_day_night($db)
{
    $data = $db->query("SELECT * FROM day_night " .
                       "ORDER by created DESC LIMIT 1");
    return is_array($data) ? $data['state'] : 'night';
}

function sensor_get_by_io_port($db, $port)
{
    return $db->query('SELECT * FROM sensors WHERE port = '. $port);
}

function sensor_get_by_io_id($db, $id)
{
    return $db->query('SELECT * FROM sensors WHERE id = '. $id);
}


function get_guard_state($db)
{
    $data = $db->query("SELECT * FROM guard_states ORDER by created DESC LIMIT 1");
    if (!$data)
        return $data;
    
    if (!is_array($data) || !isset($data['state'])) {
    	$data = array();
        $data['state'] = 'sleep';
    }

    if (isset($data['ignore_sensors']) && $data['ignore_sensors']) 
        $data['ignore_sensors'] = string_to_array($data['ignore_sensors']);
    else
        $data['ignore_sensors'] = [];

    return $data;
}