#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'config.php';
require_once 'common_lib.php';
require_once 'httpio_lib.php';
require_once 'guard_lib.php';
require_once 'player_lib.php';
require_once 'telegram_api.php';


function print_help()
{
    global $argv;
    $utility_name = $argv[0];
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

    switch ($cmd) {
    case "state":
        if (!isset($argv[2])) {
            perror("Invalid arguments: sleep/ready argument is not set\n");
            return -EINVAL;
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

            $user = user_get_by_id($user_id);
            $user_name = $user['name'];

            player_start('sounds/unlock.wav', 90);

            pnotice("Guard stoped by %s\n", $method);

            $ret = run_cmd('./io.php relay_set sbio2 1 1');
            pnotice("enable power containers: %s\n", $ret['log']);

            $ret = run_cmd('./io.php relay_set sbio1 2 1');
            pnotice("enable power RP: %s\n", $ret['log']);

            // open all padlocks
            $ret = run_cmd('./padlock.php open');
            pnotice("open all padlocks: %s\n", $ret['log']);

            /* enable lighter if night */
            $day_night = get_day_night();
            if ($day_night == 'night') {
                $ret = run_cmd('./street_light.php enable');
                pnotice("enable lighter: %s\n", $ret['log']);
            }

            $state_id = db()->insert('guard_states',
                                    ['state' => 'sleep',
                                     'user_id' => $user_id,
                                     'method' => $method]);

            $stat_text = format_global_status_for_sms(get_global_status());
            perror("Guard set sleep\n");
            telegram_send_msg(sprintf("Охрана отключена, отключил %s с помощью %s.",
                                      $user['name'], $method));

            run_cmd(sprintf("./image_sender.php current"));

            if ($method == 'cli') {
                perror("stat: %s\n", $stat_text);
                return 0;
            }

            if ($with_sms)
                sms_send('guard_disable',
                         ['user_id' => $user_id, 'groups' => ['sms_observer']],
                         ['method' => $method,
                          'user_name' => $user_name,
                          'state_id' => $state_id,
                          'global_status' => $stat_text]);

            return 0;


        case "ready":
            $user_id = 1; // stelhs
            if (isset($argv[4]))
                $user_id = $argv[4];

            $with_sms = false;
            if (isset($argv[5]) && trim($argv[5]) == 'sms')
                $with_sms = true;

            $user = user_get_by_id($user_id);
            $user_name = $user['name'];

            player_start('sounds/lock.wav', 75);

            pnotice("Guard started by %s\n", $method);

            $ret = run_cmd('./io.php relay_set sbio2 1 0');
            pnotice("disable power containers: %s\n", $ret['log']);

            $ret = run_cmd('./io.php relay_set sbio1 2 0');
            pnotice("disable power RP: %s\n", $ret['log']);

            // close all padlocks
            $ret = run_cmd('./padlock.php close');
            perror("close all padlocks: %s\n", $ret['log']);

            // check for incorrect sensors value state
            $zones = conf_guard()['zones'];
            $ignore_zones_list = [];
            foreach ($zones as $zone) {
                if (get_zone_locking_mode($zone['id']) == 'lock')
                        continue;

                $total_ports_cnt = 0;
                $incorrect_ports_cnt = 0;
                foreach ($zone['sensors'] as $row) {
                    $total_ports_cnt++;
                    $state = httpio($row['io'])->input_get_state($row['port']);
                    if ($state != $row['normal_state'])
                        $incorrect_ports_cnt++;
                }
                if ($incorrect_ports_cnt == $total_ports_cnt)
                    $ignore_zones_list[] = $zone;
            }

            if (count($ignore_zones_list)) {
                $text = "не закрыты ";
                foreach ($ignore_zones_list as $zone)
                    $text .= $zone['name'] . ' ';
                run_cmd(sprintf('./text_spech.php \'%s\' 0', $text));
                player_start(['sounds/text.wav'], 75);
            }

            /* disable lighter if this disable */
            if (conf_guard()['light_mode'] != 'auto') {
                $ret = run_cmd('./street_light.php disable 2');
                perror("disable lighter: %s\n", $ret['log']);
            }

            $ignore_zones_list_id = [];
            foreach ($ignore_zones_list as $zone)
                $ignore_sensors_list_id[] = $zone['id'];

            $state_id = db()->insert('guard_states',
                                    ['state' => 'ready',
                                     'method' => $method,
                                     'user_id' => $user_id,
                                     'ignore_zones' => array_to_string($ignore_zones_list_id)]);

            $stat_text = format_global_status_for_sms(get_global_status());
            perror("Guard set ready\n");

            telegram_send_msg(sprintf("Охрана включена, включил %s с помощью %s.",
                                      $user['name'], $method));

            run_cmd(sprintf("./image_sender.php current"));

            if ($method == 'cli') {
                perror("stat: %s\n", $stat_text);
                return 0;
            }

            if (($with_sms || count($ignore_zones_list_names)) &&
                                                    $argv[3] != 'telegram')
                sms_send('guard_enable',
                         ['user_id' => $user_id, 'groups' => ['sms_observer']],
                         ['method' => $method,
                          'user_name' => $user_name,
                          'state_id' => $state_id,
                          'global_status' => $stat_text]);

            return 0;

        default:
            perror("Invalid arguments: sleep/ready argument is not correct\n");
            return -EINVAL;
        }


    case "alarm":
        if (!isset($argv[2])) {
            perror("Invalid arguments: action_id argument is not set\n");
            return -EINVAL;
        }
        $guard_action_id = $argv[2];

        $action = db()->query('SELECT * FROM guard_actions WHERE id = ' . $guard_action_id);
        if (!is_array($action)) {
            perror("Invalid arguments: Incorrect guard_action_id. guard_action_id not found in DB\n");
            return -EINVAL;
        }

        // make snapshots
        $ret = run_cmd(sprintf('./snapshot.php %s %d_',
        conf_guard()['alarm_snapshot_dir'], $guard_action_id));
        pnotice("make snapshots: %s\n", $ret['log']);

        // run sirena
        $zone = zone_get_by_io_id($action['zone_id']);
        player_start('sounds/siren.wav', 100, $zone['alarm_time']);

        // run lighter if night
        $day_night = get_day_night();
        if ($day_night == 'night' &&
                conf_guard()['light_mode'] == 'by_sensors') {
            $ret = run_cmd(sprintf("./street_light.php enable %d",
                                   conf_guard()['light_ready_timeout']));
            pnotice("enable lighter for timeout %d: %s\n",
                    conf_guard()['light_ready_timeout'], $ret['log']);
        }

        // send to Telegram
        telegram_send_msg(sprintf("!!! Внимание, Тревога !!!\nСработала %s, событие: %d\n",
                                  $args['zone'], $guard_action_id));

        // send photos
        $ret = run_cmd(sprintf("./image_sender.php alarm %d", $guard_action_id));
        pnotice("send images to sr38: %s\n", $ret['log']);

        // send videos
        $row = db()->query('SELECT UNIX_TIMESTAMP(created) as timestamp ' .
                           'FROM guard_actions WHERE id = ' . $guard_action_id);
        run_cmd(sprintf("./video_sender.php alarm %d %d",
                        $guard_action_id,
                        $row['timestamp']),
                        true);

        // send SMS
        sms_send('alarm',
                 ['groups' => ['guard_alarm']],
                 ['zone' => $zone['name'],
                  'action_id' => $guard_action_id]);

        perror("Guard set Alarm\n");
        return 0;

    case 'stat':
        $guard_state = get_guard_state();
        dump($guard_state);
        $stat_text = format_global_status_for_sms(get_global_status());
        perror("%s\n", $stat_text);
        return 0;
    }

    return $rc;
}


$rc = main($argv);
if ($rc) {
    print_help();
    exit($rc);
}

