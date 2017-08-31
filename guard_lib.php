<?php

require_once 'config.php';
require_once 'modem3g.php';


function get_sensor_locking_mode($db, $sensor_id)
{
    $data = $db->query("SELECT * FROM blocking_sensors " .
                       "WHERE sense_id = " . $sensor_id . " " .
                       "ORDER by created DESC LIMIT 1");
    return $data ? $data['mode'] : 'unlock';
}

function sensor_get_by_io_port($db, $port)
{
    $sensors = conf_guard()['sensors'];
    foreach ($sensors as $sensor) {
        foreach ($sensor['io'] as $rows) {
            if ($rows['port'] == $port)
                return $sensor;
        }
    }
    return null;
}

function sensor_get_by_io_id($id)
{
    $sensors = conf_guard()['sensors'];
    foreach ($sensors as $sensor) {
        if ($sensor['id'] == $id)
            return $sensor;
    }
    return null;
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

    if (isset($data['user_id']))
        $data['user_name'] = user_get_by_id($db, $data['user_id'])['name'];

    if (isset($data['ignore_sensors']) && $data['ignore_sensors']) 
        $data['ignore_sensors'] = string_to_array($data['ignore_sensors']);
    else
        $data['ignore_sensors'] = [];

    return $data;
}

