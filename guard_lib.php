<?php

require_once 'config.php';
require_once 'modem3g.php';


function get_zone_locking_mode($db, $zone_id)
{
    $data = $db->query("SELECT * FROM blocking_zones " .
                       "WHERE zone_id = " . $zone_id . " " .
                       "ORDER by created DESC LIMIT 1");
    return $data ? $data['mode'] : 'unlock';
}

function zone_get_by_io_port($db, $port)
{
    $zones = conf_guard()['zones'];
    foreach ($zones as $zone) {
        foreach ($zone['sensors'] as $rows) {
            if ($rows['port'] == $port)
                return $zone;
        }
    }
    return null;
}

function zone_get_by_io_id($id)
{
    $zones = conf_guard()['zones'];
    foreach ($zones as $zone) {
        if ($zone['id'] == $id)
            return $zone;
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

    if (isset($data['ignore_zones']) && $data['ignore_zones']) 
        $data['ignore_zones'] = string_to_array($data['ignore_zones']);
    else
        $data['ignore_zones'] = [];

    return $data;
}

