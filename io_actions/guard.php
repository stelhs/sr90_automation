#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/database.php';
require_once 'config.php';
require_once 'guard_lib.php';
require_once 'sequencer_lib.php';
require_once 'common_lib.php';
$utility_name = $argv[0];


function main($argv)
{
    $rc = 0;

    if (count($argv) < 4) {
        printf("a few scripts parameters\n");
        return -EINVAL;
    }

    $io_name = $argv[1];
    $port = $argv[2];
    $port_state = $argv[3];

    // check for sensors
    $ret = zone_get_by_io_port($io_name, $port);
    if (!is_array($ret))
        return 0;
    $zone = $ret;

    $guard_state = get_guard_state();

    // define normal state for current port
    $normal_state = -1;
    foreach ($zone['sensors'] as $row)
        if ($row['io'] == $io_name && $row['port'] == $port)
            $normal_state = $row['normal_state'];

    if ($port_state == $normal_state) {
        pnotice("sensor returned to normal state\n");
        return 0;
    }

    // check activity any ports
    $total_cnt = $active_cnt = 0;
    foreach ($zone['sensors'] as $row) {
        if ($row['io'] == $io_name && $row['port'] == $port)
            continue;

        $total_cnt ++;

        $ret = db()->query(sprintf(
                           'SELECT state, id FROM io_input_actions ' .
                           'WHERE port = %d AND io_name = "%s"' .
                                 'ORDER BY id DESC LIMIT 1',
                           $row['port'], $io_name, $normal_state));
        if (!is_array($ret))
            continue;

        if ($ret['state'] != $normal_state) {
            $active_cnt ++;
            continue;
        }

        $state_id = $ret['id'];
        pnotice("state_id = %d\n", $state_id);

        $ret = db()->query(sprintf(
                           'SELECT id FROM io_input_actions ' .
                           'WHERE id = %d ' .
                                 'AND (created + INTERVAL %d SECOND) > now()',
                           $state_id, $zone['diff_interval']));
        if (!is_array($ret))
            continue;

        if (isset($ret['id']))
            $active_cnt ++;
    }

    // if not all ports active
    if ($active_cnt != $total_cnt) {
        pnotice("not all sensors is active\n");
        if ($guard_state['state'] == 'sleep')
            return 0;

        telegram_send('false_alarm', ['name' => $zone['name'],
                                      'io' => $io_name,
                                      'port' => $port]);
        run_cmd(sprintf("./image_sender.php current"));
        return 0;
    }

    // run lighter if night
    $day_night = get_day_night();
    if ($zone['run_lighter'] &&
             conf_guard()['light_mode'] == 'by_sensors' &&
             $day_night == 'night' &&
             $guard_state['state'] == 'ready') {
        $light_interval = conf_guard()['light_ready_timeout'];
        pnotice("run lighter for %d seconds\n", $light_interval);
        $ret = run_cmd(sprintf("./street_light.php enable %d",
                               $light_interval));
        pnotice("street_light return: %s\n", $ret['log']);
    }

    // check for sensor is lock
    $zone_locking_mode = get_zone_locking_mode($zone['id']);
    if ($zone_locking_mode == 'lock') {
        printf("zone %d is locked\n", $zone['id']);
        return 0;
    }

    // store guard action
    $action_id = db()->insert('guard_actions',
                              ['zone_id' => $zone['id'],
                               'alarm' => 0,
                               'guard_state' => $guard_state['state']]);

    // check for sensor is ignored
    if ($guard_state['ignore_zones'])
   		foreach ($guard_state['ignore_zones'] as $ignore_zone_id)
   	        if ($ignore_zone_id == $zone['id']) {
                pnotice("zone %d is ignored\n", $zone['id']);
                return 0;
            }

    // check guard state and initiate ALARM if needed
    if ($guard_state['state'] == 'sleep') {
        pnotice("guard is sleeped\n");
        return 0;
    }

    // ignore ALARM if set ready state a little time ago
    $ret = db()->query(sprintf("SELECT id FROM guard_states " .
                               "WHERE state = 'ready' AND " .
                               "(created + interval %d second) > now() " .
				 	           "ORDER BY created DESC LIMIT 1",
                               conf_guard()['ready_set_interval']));
    if (isset($ret['id'])) {
        pnotice("alarm was ignored because ready state a little time ago\n");
        return 0;
    }

    // ignore ALARM if already in ALARM state
    $ret = db()->query("SELECT id, zone_id FROM guard_actions " .
                       "WHERE alarm = 1 " .
			           "ORDER BY created DESC LIMIT 1");
    if (isset($ret['zone_id'])) {
        $alarm_id = $ret['id'];
        $alarm_zone = zone_get_by_io_id($ret['zone_id']);
        $ret = db()->query(sprintf("SELECT id FROM guard_actions " .
                                   "WHERE id = %d " .
                                       "AND (created + interval %d second) > now() ",
                                   $alarm_id, $alarm_zone['alarm_time']));
        if (isset($ret['id'])) {
            pnotice("alarm was ignored because system already in alarm state\n");
            return 0;
        }
    }

    // update guard action to alarm state
    db()->update('guard_actions', $action_id, ['alarm' => 1]);

    // do ALARM!
    $ret = run_cmd(sprintf("./guard.php alarm %d", $action_id));
    pnotice("do ALARM: %s\n", $ret['log']);

    return 0;
}


exit(main($argv));