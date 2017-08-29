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
    global $sensors;
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

    // define normal state for current port
    $normal_state = -1;
    foreach ($sensor['io'] as $row)
        if ($row['port'] == $port)
            $normal_state = $row['normal_state'];

    if ($port_state == $normal_state) {
        printf("sensor returned to normal state\n");
        $rc = 0;
        goto out;
    }

    // check activity any ports
    $total_cnt = $active_cnt = 0; 
    foreach ($sensor['io'] as $row) {
        if ($row['port'] == $port)
            continue;

        $total_cnt ++;

        $ret = $db->query(sprintf(
                           'SELECT state, id FROM io_input_actions ' .
                           'WHERE port = %d ' .
                                 'ORDER BY id DESC LIMIT 1',
                           $row['port'], $normal_state));
        if (!is_array($ret))
            continue;

        if ($ret['state'] != $normal_state) {
            $active_cnt ++;
            continue;
        }

        $state_id = $ret['id'];
        printf("state_id = %d\n", $state_id);

        $ret = $db->query(sprintf(
                           'SELECT id FROM io_input_actions ' .
                           'WHERE id = %d ' .
                                 'AND (created + INTERVAL %d SECOND) > now()',
                           $state_id, $sensor['diff_interval']));
        if (!is_array($ret))
            continue;

        if (isset($ret['id']))
            $active_cnt ++;
    }

    // if not all ports active 
    if ($active_cnt != $total_cnt) {
        printf("not all sensors is active\n");
        $rc = 0;
        goto out;
    }

    $guard_state = get_guard_state($db);

    // run lighter if night
    $day_night = get_day_night();
    if ($sensor['run_lighter'] &&
             conf_guard()['light_mode'] == 'by_sensors' &&
             $day_night == 'night' &&
             $guard_state['state'] == 'ready') {
        $light_interval = conf_guard()['light_ready_timeout'] * 1000;
        printf("run lighter for %d seconds\n", $light_interval);
        $ret = run_cmd(sprintf("./street_light.php %d %d", 
                               $sensor['id'], $light_interval));
        printf("do ALARM: %s\n", $ret['log']);
    }

    // check for sensor is lock
    $sense_locking_mode = get_sensor_locking_mode($db, $sensor['id']);
    if ($sense_locking_mode == 'lock') {
        printf("sensor %d is locked\n", $sensor['id']);
        $rc = 0;
        goto out;
    }

    // store guard action
    $action_id = $db->insert('guard_actions', 
                             array('sense_id' => $sensor['id'],
                                   'alarm' => 0,
                                   'guard_state' => $guard_state['state']));

    // check for sensor is ignored
    if ($guard_state['ignore_sensors'])
   		foreach ($guard_state['ignore_sensors'] as $ignore_sensor_id)
   			if ($ignore_sensor_id == $sensor['id']) {
                printf("sensor %d is ignored\n", $sensor['id']);
                $rc = 0;
                goto out;
            }

    // check guard state and initiate ALARM if needed
    if ($guard_state['state'] == 'sleep') {
        printf("guard is sleeped\n");
        goto out;
    }

    // ignore ALARM if set ready state a little time ago
    $ret = $db->query(sprintf("SELECT id FROM guard_states " .
                              "WHERE state = 'ready' AND " .
                              "(created + interval %d second) > now() " .
					          "ORDER BY created DESC LIMIT 1", 
                              conf_guard()['ready_set_interval']));
    if (isset($ret['id'])) {
        printf("alarm was ignored because ready state a little time ago\n");
        goto out;
    }

    // ignore ALARM if already in ALARM state
    $ret = $db->query("SELECT id, sense_id FROM guard_actions " .
                      "WHERE alarm = 1 " .
			          "ORDER BY created DESC LIMIT 1");
    if (isset($ret['sense_id'])) {
        $alarm_id = $ret['id'];
        $alarm_sensor = sensor_get_by_io_id($ret['sense_id']);
        $ret = $db->query(sprintf("SELECT id FROM guard_actions " .
                                  "WHERE id = %d " .
                                      "AND (created + interval %d second) > now() ", 
                                  $alarm_id, $alarm_sensor['alarm_time']));
        if (isset($ret['id'])) {
            printf("alarm was ignored because system already in alarm state\n");
            goto out;
        }
    }

    // update guard action to alarm state
    $db->update('guard_actions', $action_id, ['alarm' => 1]);

    // do ALARM!
    $ret = run_cmd(sprintf("./guard.php alarm %d", $action_id));
    printf("do ALARM: %s\n", $ret['log']);

out:
    $db->close();
    return $rc;
}


return main($argv);