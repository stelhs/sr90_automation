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
                 "\t\t state: set guard state. Args: sleep/ready, method, user_id\n" . 
                 "\t\t\texample: $utility_name state sleep cli 1\n" .
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
            $user_name = "";
            $user_id = 0;
            $user_phone = null;
            if (isset($argv[4])) {
                $user_id = $argv[4];
                $user = user_get_by_id($db, $user_id);
                $user_name = $user['name'];
                $user_phone = string_to_array($user['phones'])[0];
            }

            msg_log(LOG_NOTICE, "Guard stoped by " . $method);
            // two beep by sirena
            sequncer_stop(conf_guard()['sirena_io_port']);
            sequncer_start(conf_guard()['sirena_io_port'],
                           array(100, 100, 100, 0));

            // get list phones for SMS subscribers
            $users = $db->query_list('SELECT * FROM users '.
                                     'WHERE guard_observer = 1');
            $list_phones = array();
            foreach ($users as $user)
                $list_phones[] = string_to_array($user['phones'])[0];

            if ($user_phone && !in_array($user_phone, $list_phones))
                $list_phones[] = $user_phone;

            notify_send_by_sms('guard_disable',
                               $list_phones,
                               array('method' => $method,
                                     'user_name' => $user_name));

            $db->insert('guard_states', 
                  array('state' => 'sleep',
                        'user_id' => $user_id,
                        'method' => $method));
            printf("Guard set sleep\n");
            goto out;


        case "ready":
            $user_name = "";
            $user_id = 0;
            $user_phone = null;
            if (isset($argv[4])) {
                $user_id = $argv[4];
                $user = user_get_by_id($db, $user_id);
                $user_name = $user['name'];
                $user_phone = string_to_array($user['phones'])[0];
            }

            msg_log(LOG_NOTICE, "Guard started by " . $method);

            sequncer_stop(conf_guard()['sirena_io_port']);
            $sensors = $db->query_list('SELECT * FROM sensors');

            // check for incorrect sensor value state
            $ignore_sensors_list = [];
            foreach ($sensors as $sensor) {
                if (get_sensor_locking_mode($db, $sensor['id']) == 'lock')
                    continue;
    
                $port_state = $mio->input_get_state($sensor['port']);
                if ($port_state != $sensor['normal_state'])
                    $ignore_sensors_list[] = $sensor['id'];
            }

            if (!count($ignore_sensors_list)) {
                // one beep by sirena
                sequncer_start(conf_guard()['sirena_io_port'],
                               array(200, 0));
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

            // get list phones for SMS subscribers
            $users = $db->query_list('SELECT * FROM users '.
                                     'WHERE guard_observer = 1');
            $list_phones = array();
            foreach ($users as $user)
                $list_phones[] = string_to_array($user['phones'])[0];

            if ($user_phone && !in_array($user_phone, $list_phones))
                $list_phones[] = $user_phone;

            notify_send_by_sms('guard_enable',
                               $list_phones, 
                               array('method' => $method,
                                     'user_name' => $user_name,
                                     'ignore_sensors' => $ignore_sensors_list_names));

            $db->insert('guard_states', 
                        array('state' => 'ready',
                              'method' => $method,
							  'user_id' => $user_id,
                              'ignore_sensors' => array_to_string($ignore_sensors_list)));
            printf("Guard set ready\n");
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
        $action_id = $argv[2];

        $action = $db->query('SELECT * FROM sensor_actions WHERE id = ' . $action_id);
        if (!is_array($action)) {
            printf("Invalid arguments: Incorrect action_id. action_id not found in DB\n");
            $rc = -EINVAL;
            goto out;
        }

        $sensor = sensor_get_by_io_id($db, $action['sense_id']);
        run_cmd(sprintf('./snapshot.php %s %d_',
                        conf_guard()['camera_dir'], $action_id));

        // run sirena
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

        $db->insert('guard_alarms', 
                    array('action_id' => $action_id));

        // get list phones for SMS subscribers
        $users = $db->query_list('SELECT * FROM users '.
                                 'WHERE guard_alarm = 1');
        $list_phones = array();
        foreach ($users as $user) {
            $phones = string_to_array($user['phones']);
            foreach ($phones as $phone)
                $list_phones[] = $phone;
        }

        // send SMS
        notify_send_by_sms('alarm',
                           $list_phones,
                           array('sensor' => $sensor['name'],
                                 'action_id' => $action_id));
        printf("Guard set Alarm\n");
        goto out;

    case 'stat':
        $guard_state = get_guard_state($db);
        dump($guard_state);
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

