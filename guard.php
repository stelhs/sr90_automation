#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'config.php';
require_once 'mod_io_lib.php';
require_once 'guard_lib.php';
require_once 'sequencer_lib.php';
require_once 'server_control_lib.php';

$utility_name = $argv[0];

function print_help()
{
    global $utility_name;
    echo "Usage: $utility_name <command> <args>\n" .
             "\tcommands:\n" .
                 "\t\t state: set guard state. Args: sleep/ready, method, user_id, [sms]\n" . 
                 "\t\t\texample: $utility_name state sleep cli 1 sms\n" .
                 "\t\t alarm: Execute ALARM. Args: action_id\n" . 
                 "\t\t\texample: $utility_name alarm 71\n" .
                 "\t\t stat: Return status information about Guard system\n" . 

             "\n\n";
}



function main($argv)
{
    $rc = 0;
    if (!isset($argv[1])) {
        return -EINVAL;
    }

    $cmd = $argv[1];

    $db = new Database;
    $rc = $db->connect(conf_db());
    if ($rc) {
        printf("can't connect to database");
        return -EBASE;
    }

    $mio = new Mod_io($db);

    switch ($cmd) {
    case "state":
        if (!isset($argv[2])) {
            printf("Invalid arguments: sleep/ready argument is not set\n");
            $rc = -EINVAL;
            goto out;
        }
        $new_mode = $argv[2];

        $method = 'cli';
        if (isset($argv[3]))
            $method = $argv[3];

        switch ($new_mode) {
        case "sleep":
            $user_id = 1; // stelhs
            if (isset($argv[4]))
                $user_id = $argv[4];

            $with_sms = false;
            if (isset($argv[5]) && trim($argv[5]) == 'sms')
                $with_sms = true;

            $user = user_get_by_id($db, $user_id);
            $user_name = $user['name'];
            $user_phone = $user['phones'][0];

            msg_log(LOG_NOTICE, "Guard stoped by " . $method);

            // enable all cam in doors
            foreach (conf_guard()['doors'] as $io_port) { 
                $rc = $mio->relay_set_state($io_port, 1);
                if ($rc < 0)
                    printf("Can't set relay state %d\n", $io_port);
            }

            // two beep by sirena
            sequncer_stop(conf_guard()['sirena_io_port']);
            sequncer_start(conf_guard()['sirena_io_port'],
                           array(100, 100, 100, 0));

            $state_id = $db->insert('guard_states',
                                    array('state' => 'sleep',
                                          'user_id' => $user_id,
                                          'method' => $method));

            $list_phones = get_users_phones_by_access_type($db, 'sms_observer');
            if ($user_phone && !in_array($user_phone, $list_phones))
                $list_phones[] = $user_phone;

            $stat_text = get_formatted_global_status($db);
            printf("Guard set sleep\n");

            if ($method == 'cli') {
                printf("stat: %s\n", $stat_text);
                goto out;
            }

            if ($with_sms)
                notify_send_by_sms('guard_disable',
                                   $list_phones,
                                   array('method' => $method,
                                         'user_name' => $user_name,
                                         'state_id' => $state_id,
                                         'global_status' => $stat_text));

            /* enable lighter if night */
            $day_night = get_day_night($db);
            if ($day_night == 'night')
                $mio->relay_set_state(conf_guard()['lamp_io_port'], 1);

            goto out;


        case "ready":
            $user_id = 1; // stelhs
            if (isset($argv[4]))
                $user_id = $argv[4];

            $with_sms = false;
            if (isset($argv[5]) && trim($argv[5]) == 'sms')
                $with_sms = true;

            $user = user_get_by_id($db, $user_id);
            $user_name = $user['name'];
            $user_phone = $user['phones'][0];

            msg_log(LOG_NOTICE, "Guard started by " . $method);

            $sensors = $db->query_list('SELECT * FROM sensors');

            // disable all cam in doors
            foreach (conf_guard()['doors'] as $io_port) { 
                $rc = $mio->relay_set_state($io_port, 0);
                if ($rc < 0)
                    printf("Can't set relay state %d\n", $io_port);
            }

            // check for incorrect sensor value state
            $ignore_sensors_list = [];
            foreach ($sensors as $sensor) {
                if (get_sensor_locking_mode($db, $sensor['id']) == 'lock')
                        continue;

                $port_state = $mio->input_get_state($sensor['port']);
                if ($port_state != $sensor['normal_state'])
                    $ignore_sensors_list[] = $sensor['id'];
            }

            sequncer_stop(conf_guard()['sirena_io_port']);
            if (!count($ignore_sensors_list)) {
                // one beep by sirena
                sequncer_start(conf_guard()['sirena_io_port'], array(200, 0));
            } else {
                // two beep by sirena
                sequncer_start(conf_guard()['sirena_io_port'],
                               array(200, 200, 1000, 0));
            }

            $ignore_sensors_list_names = array();
            foreach ($ignore_sensors_list as $sensor_id) {
                $sensor = sensor_get_by_io_id($db, $sensor_id);
                $ignore_sensors_list_names[] = $sensor['name'];
            }

            $state_id = $db->insert('guard_states',
                                    array('state' => 'ready',
                                          'method' => $method,
                                          'user_id' => $user_id,
                                          'ignore_sensors' => array_to_string($ignore_sensors_list)));

            $list_phones = get_users_phones_by_access_type($db, 'sms_observer');
            if ($user_phone && !in_array($user_phone, $list_phones))
            $list_phones[] = $user_phone;

            $stat_text = get_formatted_global_status($db);
            printf("Guard set ready\n");
            if ($method == 'cli') {
                printf("stat: %s\n", $stat_text);
                goto out;
            }

            if ($with_sms || count($ignore_sensors_list_names))
                notify_send_by_sms('guard_enable',
                                    $list_phones,
                                    array('method' => $method,
                                          'user_name' => $user_name,
                                          'ignore_sensors' => $ignore_sensors_list_names,
                                          'state_id' => $state_id,
                                          'global_status' => $stat_text));

            /* disable lighter if this disable */
            if (conf_guard()['light_mode'] != 'auto')
                $mio->relay_set_state(conf_guard()['lamp_io_port'], 0);

            goto out;

        default:
            printf("Invalid arguments: sleep/ready argument is not correct\n");
            $rc = -EINVAL;
        }


    case "alarm":
        if (!isset($argv[2])) {
            printf("Invalid arguments: action_id argument is not set\n");
            $rc = -EINVAL;
            goto out;
        }
        $sensor_action_id = $argv[2];

        $action = $db->query('SELECT * FROM sensor_actions WHERE id = ' . $sensor_action_id);
        if (!is_array($action)) {
            printf("Invalid arguments: Incorrect sensor_action_id. sensor_action_id not found in DB\n");
            $rc = -EINVAL;
            goto out;
        }

        // run sirena
        $sensor = sensor_get_by_io_id($db, $action['sense_id']);
        sequncer_stop(conf_guard()['sirena_io_port']);
        sequncer_start(conf_guard()['sirena_io_port'],
        array($sensor['alarm_time'] * 1000, 0));

        // run lighter if night
        $day_night = get_day_night($db);
        if ($day_night == 'night' &&
                conf_guard()['light_mode'] == 'by_sensors') {
            $light_interval = conf_guard()['light_ready_timeout'] * 1000;
            sequncer_stop(conf_guard()['lamp_io_port']);
            sequncer_start(conf_guard()['lamp_io_port'],
                           array($light_interval, 0));
        }

        // store alarm to database
        $alarm_action_id = $db->insert('guard_alarms',
        array('action_id' => $sensor_action_id));

        // make snapshots
        run_cmd(sprintf('./snapshot.php %s %d_',
                        conf_guard()['alarm_snapshot_dir'], $alarm_action_id));
        // send SMS
        $list_phones = get_all_users_phones_by_access_type($db, 'guard_alarm');
        notify_send_by_sms('alarm',
                           $list_phones,
                           array('sensor' => $sensor['name'],
                                 'action_id' => $alarm_action_id));
        printf("Guard set Alarm\n");
        goto out;

    case 'stat':
        $guard_state = get_guard_state($db);
        dump($guard_state);
        $stat_text = get_formatted_global_status($db);
        printf("%s\n", $stat_text);
        goto out;
    }

    out:
    $db->close();
    return $rc;
}


$rc = main($argv);
if ($rc) {
    print_help();
}

