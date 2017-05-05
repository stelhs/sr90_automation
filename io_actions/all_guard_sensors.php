#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/database.php';
require_once 'config.php';
require_once 'guard_lib.php';
require_once 'sequencer_lib.php';
require_once 'server_control_lib.php';
$utility_name = $argv[0];


function main($argv)
{
    $rc = 0;
    
    if (count($argv) < 3) {
        printf("a few scripts parameters\n");
        return -EINVAL;
    }
    
    $port = $argv[1];
    $port_state = $argv[2];
    
    $db = new Database;
    $rc = $db->connect(conf_db());
    if ($rc) {
        printf("can't connect to database");
        return -EBASE;
    }
    
    // check for sensors
    $ret = sensor_get_by_io_port($db, $port);
    if (!is_array($ret)) {
        $rc = 0;
        goto out;
    }
    $sensor = $ret;
    $guard_state = get_guard_state($db);
    
    // run lighter if night
    $day_night = get_day_night();
    if ($sensor['run_lighter'] &&
             conf_guard()['light_mode'] == 'by_sensors' &&
             $day_night == 'night' &&
             $guard_state['state'] == 'ready') {
        $light_interval = conf_guard()['light_ready_timeout'] * 1000;
        sequncer_stop(conf_guard()['lamp_io_port']);
        sequncer_start(conf_guard()['lamp_io_port'], 
                       array($light_interval, 0));
    }

    // check for sensor is lock
    $sense_locking_mode = get_sensor_locking_mode($db, $sensor['id']);
    if ($sense_locking_mode == 'lock') {
        $rc = 0;
        goto out;
    }

    // store sensor state
    $sensor_state = ($port_state == $sensor['normal_state'] ? 'normal' : 'action');
    $action_id = $db->insert('sensor_actions', 
                             array('sense_id' => $sensor['id'],
                                   'state' => $sensor_state,
                                   'guard_state' => $guard_state['state']));

    // make snapshots
    run_cmd(sprintf('./snapshot.php %s %d_',
                    conf_guard()['sensor_snapshot_dir'], $action_id));

    // check for sensor is ignored
    if ($guard_state['ignore_sensors'])
   		foreach ($guard_state['ignore_sensors'] as $ignore_sensor_id)
   			if ($ignore_sensor_id == $sensor['id']) {
                $rc = 0;
                goto out;
            }

    // check guard state and initiate ALARM if needed
    if ($guard_state['state'] == 'sleep')
        goto out;

    // ignore ALARM if set ready state a little time ago
    $ret = $db->query(sprintf("SELECT id FROM guard_states " .
                              "WHERE state = 'ready' AND " .
                              "(created + interval %d second) > now() " .
					          "ORDER BY created DESC LIMIT 1", 
                              conf_guard()['ready_set_interval']));
    if (isset($ret['id']))
        goto out;

    // ignore ALARM if already in ALARM state
    $ret = $db->query(sprintf("SELECT id FROM guard_alarms " .
                              "WHERE (created + interval %d second) > now() " .
					          "ORDER BY created DESC LIMIT 1", 
                              $sensor['alarm_time']));
    if (isset($ret['id']))
        goto out;

    // do ALARM!
    run_cmd(sprintf("./guard.php alarm %d", $action_id));
out:
    $db->close();
    return $rc;
}


return main($argv);