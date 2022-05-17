<?php

require_once 'config.php';
require_once 'io_api.php';
require_once 'common_lib.php';

define("CHARGER_STAGE_FILE", "/tmp/battery_charge_stage");

define("CHARGER_DISABLE_FILE", "/tmp/charger_disable");
define("CHARGE_LASTTIME_FILE", "/tmp/battery_charge_lasttime");
define("DISCHARGE_LASTTIME_FILE", "/tmp/battery_discharge_lasttime");
define("LOW_BATT_VOLTAGE_FILE", "/tmp/battery_low_voltage");
define("EXT_POWER_STATE_FILE", "/run/ext_power_state");

class Power {
    function battery_info()
    {
        $ret = io()->board('mbio1')->battery_info();
        if ($ret[0])
            return NULL;

        return $ret[1];
    }


    function power_state()
    {
        iop('ext_power')->disable_logs();
        iop('ups_220vac')->disable_logs();

        $power['input'] = iop('ext_power')->state()[0];
        $power['ups'] = iop('ups_220vac')->state()[0];

        iop('ext_power')->enable_logs();
        iop('ups_220vac')->enable_logs();
        return $power;
    }

    function ups_state()
    {
        $stat = [];
        $stat['vdc_out_state'] = iop('ups_250vdc')->state()[0];
        $stat['standby_state'] = iop('ups_14vdc')->state()[0];
        $stat['charger_state'] = 'charge_stage1';
        if (file_exists(CHARGER_STAGE_FILE))
            $stat['charger_state'] = file_get_contents(CHARGER_STAGE_FILE);
        return $stat;
    }


    /**
     * Get duration between UPS power loss and UPS power resume
     */
    function ups_duration()
    {
        $data = db()->query("SELECT UNIX_TIMESTAMP(created) as created, state " .
                                            "FROM ext_power_log WHERE type='ups' " .
                                            "ORDER BY id DESC LIMIT 1");
        if (!is_array($data) ||
            $data['state'] == 1)
            return NULL;

        $last_ext_power_state = $data['created'];
        $now = time();
        return $now - $last_ext_power_state;
    }

    function uptime()
    {
        $ret = run_cmd('uptime');
        preg_match('/up (.+),/U', $ret['log'], $mathes);
        return $mathes[1];
    }

    function server_reboot($method, $user_id = NULL)
    {
        global $log;
        if ($method == "SMS")
            sms_send('reboot',
                     ['user_id' => $user_id,
                      'groups' => ['sms_observer']],
                     $method);

        $text = sprintf("Сервер ушел на перезагрузку по запросу %s", $method);
        $log->info("server_reboot(): server going to reboot");

        tn()->send_to_admin($text);
        if(DISABLE_HW)
            return;
        run_cmd('halt');
        for(;;);
    }

    function halt_all_systems()
    {
        global $log;
        $log->info("halt_all_systems()\n", $content);

        if ($this->is_halt_all_systems()) {
            $log->err("is already halted\n");
            return;
        }

        if (DISABLE_HW) {
            perror("FAKE: halt all systems, goodbuy. For undo - remove %s\n",
                   FAKE_HALT_ALL_SYSTEMS_FILE);
            file_put_contents(FAKE_HALT_ALL_SYSTEMS_FILE, '');
            return;
        }
        run_cmd("halt");
    }

    function is_halt_all_systems() {
        return file_exists(FAKE_HALT_ALL_SYSTEMS_FILE);
    }

    function stat_text()
    {
        $tg = '';
        $sms = '';

        $tg .= sprintf("Uptime: %s\n", $this->uptime());
        $sms .= sprintf("Uptime:%s, ", $this->uptime());

        $info = $this->battery_info();
        if (!is_array($info)) {
            $tg .= sprintf("ошибка АКБ\n");
            $sms .= sprintf("ошибка АКБ, ");
        } else {
            $tg .= sprintf("АКБ: %.2fv, %.2fA\n",
                             $info['voltage'],
                             $info['current']);

            $sms .= sprintf("АКБ: %.2fv,%.2fA, ",
                             $info['voltage'],
                             $info['current']);
        }

        $stat = $this->power_state();
        $tg .= sprintf("Питание на вводе: %s\n" .
                       "Питание на ИБП: %s\n" ,
                         $stat['input'] ? 'присутствует' : 'отсутствует',
                         $stat['ups'] ? 'присутствует' : 'отсутствует');
        $sms .= sprintf("Внешн. пит:%d, пит.ИБП:%d, ",
                         $stat['input'],
                         $stat['ups']);

        $stat = $this->ups_state();
        $tg .= sprintf("Выходное питание ИБП: %s\n" .
                       "Дежурное питание ИБП: %s\n" .
                       "Состояние ИБП: %s\n",
                         $stat['vdc_out_state'] ? 'присутствует' : 'отсутствует',
                         $stat['standby_state'] ? 'присутствует' : 'отсутствует',
                         $stat['charger_state']);

        $sms .= sprintf("250VDC:%d, 14VDC:%d, ups_stat:%s",
                         $stat['vdc_out_state'],
                         $stat['standby_state'],
                         $stat['charger_state']);

        return [$tg, $sms];
    }
}


function power()
{
    static $power = NULL;
    if (!$power)
        $power = new Power;

    return $power;
}

class Ext_power_io_handler implements IO_handler {
    function name() {
        return "power_monitor";
    }

    function trigger_ports() {
        return ['ext_power' => ['ext_power' => 2],
                'ups_220vac' => ['ups_220vac' => 2],
                'ups_250vdc' => ['ups_250vdc' => 0],
                //'in_test1' => ['in_test1' => 0]
                ];
    }
/*
    function in_test1($pname, $state)
    {
        iop('out_test1')->up();
    }*/

    function ext_power($pname, $state)
    {
        $row = db()->query('SELECT state FROM ext_power_log ' .
                           'WHERE type = "input" ' .
                           'ORDER BY id DESC LIMIT 1');

        $prev_state = $row['state'];
        if ($state == $prev_state)
            return 0;

        switch ($state) {
        case 1:
            $msg = 'Питание на вводе восстановлено';
            break;
        case 0:
            $msg = 'Питание на вводе отключено';
            break;
        default:
            return;
        }
        tn()->send_to_admin($msg);
        db()->insert('ext_power_log',
                     ['state' => $state,
                      'type' => 'input']);
    }

    function ups_220vac($pname, $state)
    {
        $row = db()->query('SELECT state FROM ext_power_log ' .
                           'WHERE type = "ups" ' .
                           'ORDER BY id DESC LIMIT 1');

        $prev_state = $row['state'];
        if ($state == $prev_state)
            return 0;

        switch ($state) {
            case 1:
                $msg = 'Питание ИБП восстановлено';
                break;
            case 0:
                $msg = 'Питание ИБП отключено';
                break;
            default:
                return ;
        }

        tn()->send_to_admin($msg);
        db()->insert('ext_power_log',
                     ['state' => $state,
                      'type' => 'ups']);
    }

    function ups_250vdc($pname, $state)
    {
        $msg = 'Ошибка ИБП: отсутсвует выходное напряжение 250vdc';
        tn()->send_to_admin($msg);
    }
}



class Ups_periodically implements Periodically_events {
    function name() {
        return "ups";
    }

    function interval() {
        return 1;
    }

    function __construct() {
        $this->log = new Plog('sr90:ups');
    }

    function switch_to_discharge() {
        iop('charge_discharge')->up();
        file_put_contents(DISCHARGE_LASTTIME_FILE, time());
    }

    function switch_to_charge() {
        iop('charge_discharge')->down();
        iop('charger_en')->down();
        sleep(1);
        iop('charger_en')->up();
        file_put_contents(CHARGE_LASTTIME_FILE, time());
    }

    function set_low_current_charge()
    {
        iop('charger_1.5a')->down();
        iop('charger_3a')->down();
    }

    function set_middle_current_charge()
    {
        iop('charger_1.5a')->up();
        iop('charger_3a')->down();
    }

    function set_high_current_charge()
    {
        iop('charger_1.5a')->up();
        iop('charger_3a')->up();
    }

    function enable_charge()
    {
        iop('charger_en')->down();
        sleep(1);
        iop('charger_en')->up();
    }

    function disable_charge()
    {
        if (iop('charger_en')->state()[0])
            $this->switch_to_charge();
        $this->set_low_current_charge();
        iop('charger_en')->down();
    }

    function micro_cycling_state()
    {
        $charge_lasttime = $discharge_lasttime = 0;
        if (file_exists(CHARGE_LASTTIME_FILE))
            $charge_lasttime = file_get_contents(CHARGE_LASTTIME_FILE);

        if (file_exists(DISCHARGE_LASTTIME_FILE))
            $discharge_lasttime = file_get_contents(DISCHARGE_LASTTIME_FILE);

        if ($charge_lasttime > $discharge_lasttime) {
            $switch_interval = time() - $charge_lasttime;
            $mode = 'charge';
        }
        else {
            $switch_interval = time() - $discharge_lasttime;
            $mode = 'discharge';
        }
        return ['mode' => $mode, 'interval' => $switch_interval];
    }


    function switch_mode_to_stage1($batt_info, $reason = "")
    {
        printf("switch_mode_to_stage1\n");
        $this->switch_to_charge();
        $this->set_high_current_charge();
        $this->enable_charge();
        file_put_contents(CHARGER_STAGE_FILE, 'charge_stage1');
        $msg = sprintf('Включен заряд током 3A, напряжение на АКБ %.2f',
                       $batt_info['voltage']);
        tn()->send_to_admin($msg);
        db()->insert('ups_actions', ['stage' => 'charge1', 'reason' => $reason]);
    }

    function switch_mode_to_stage2($batt_info)
    {
        $this->switch_to_charge();
        $this->set_middle_current_charge();
        $this->enable_charge();
        file_put_contents(CHARGER_STAGE_FILE, 'charge_stage2');
        $msg = sprintf('Включен заряд током 1.5A, напряжение на АКБ %.2f',
                       $batt_info['voltage']);
        tn()->send_to_admin($msg);
        db()->insert('ups_actions', ['stage' => 'charge2']);
    }

    function switch_mode_to_stage3($batt_info)
    {
        $this->switch_to_charge();
        $this->set_low_current_charge();
        $this->enable_charge();
        file_put_contents(CHARGER_STAGE_FILE, 'charge_stage3');
        $msg = sprintf('Включен заряд током 0.5A, напряжение на АКБ %.2f',
                       $batt_info['voltage']);
        tn()->send_to_admin($msg);
        db()->insert('ups_actions', ['stage' => 'charge3']);
    }

    function switch_mode_to_ready($batt_info)
    {
        file_put_contents(CHARGER_STAGE_FILE, 'ready');
        $this->disable_charge();
        $msg = sprintf('Заряд окончен, напряжение на АКБ %.2fv',
                       $batt_info['voltage']);
        tn()->send_to_admin($msg);
        db()->insert('ups_actions', ['stage' => 'idle']);
    }

    function switch_mode_to_stage4($batt_info)
    {
        $this->set_low_current_charge();
        $this->switch_to_charge();
        file_put_contents(CHARGER_STAGE_FILE, 'charge_stage4');
        $msg = sprintf('Напряжение на АКБ снизилось до %.2fv, ' .
                       'включился капельный дозаряд до 14.4v',
                       $batt_info['voltage']);
        tn()->send_to_admin($msg);
        db()->insert('ups_actions', ['stage' => 'recharging']);
    }


    function stop_charger()
    {
        $this->disable_charge();
        file_put_contents(CHARGER_DISABLE_FILE, "");
    }

    function restart_charger()
    {
        unlink_safe(CHARGER_DISABLE_FILE);
        unlink_safe(CHARGER_STAGE_FILE);
    }

    function do() {
        if (power()->is_halt_all_systems()) {
            perror("systems is halted\n");
            return 0;
        }

        $power_states = power()->power_state();
        $ups_power_state = isset($power_states['ups']) ? $power_states['ups'] : -1;
        $input_power_state = isset($power_states['input']) ? $power_states['input'] : -1;
        pnotice("current_ups_power_state = %d\n", $ups_power_state);
        pnotice("current_input_power_state = %d\n", $input_power_state);

        // check for external power is absent
        $prev_state = FALSE;
        if (file_exists(EXT_POWER_STATE_FILE))
            $prev_state = file_get_contents(EXT_POWER_STATE_FILE);

        if ($prev_state === FALSE) {
            file_put_contents(EXT_POWER_STATE_FILE, $ups_power_state);
            $this->log->err("prev_state unknown\n");
            return 0;
        }

        if ($ups_power_state != $prev_state) {
            $this->log->info("external power changed to %d\n", $ups_power_state);
            file_put_contents(EXT_POWER_STATE_FILE, $ups_power_state);
            if (!$ups_power_state) {
                if (iop('ups_break_power')->state()[0])
                    $reason = "external UPS power is off forcibly";
                else
                    $reason = "external power is absent";
                db()->insert('ups_actions', ['stage' => 'discarge', 'reason' => $reason]);
                tn()->send_to_admin("остановка зарядки из за ups_power_state, ups_power_state = %d, prev_state = %d",
                                                $ups_power_state, $prev_state);
                $this->log->info("stop_charger\n");
                $this->stop_charger();
                return 0;
            }
            $this->log->info("restart_charger\n");
            $this->restart_charger();
        }

        $batt_info = power()->battery_info();
        if (!is_array($batt_info)) {
            $this->log->err("can't get baterry info\n");
            $this->stop_charger();
            tn()->send_to_admin("Ошибка получения инфорамции о АКБ");
            return -1;
        }

        $voltage = $batt_info['voltage'];
        pnotice("voltage = %f\n", $voltage);
        $current = $batt_info['current'];
        pnotice("current = %f\n", $current);

        if ($voltage <= 0)
            return -1;

        if ($voltage < 11.88) {
            pnotice("voltage drop bellow 11.88v\n");
            $notified = 0;
            if (file_exists(LOW_BATT_VOLTAGE_FILE))
                $notified = file_get_contents(LOW_BATT_VOLTAGE_FILE);

            if ((time() - $notified) > 300) {
                $msg = sprintf('Низкий заряд АКБ. Напряжение на АКБ %.2fv',
                    $voltage);
                tn()->send_to_admin($msg);
                file_put_contents(LOW_BATT_VOLTAGE_FILE, time());
                $this->restart_charger();
            }
        } else
            unlink_safe(LOW_BATT_VOLTAGE_FILE);

        if (!$ups_power_state and $input_power_state) {
            $duration = power()->ups_duration();
            iop('ups_break_power')->down();
            $this->log->info("UPS test is success finished. Duration %d seconds\n", $duration);
            $msg = sprintf("Испытание ИБП завершено.\n" .
                           "Система проработала от АКБ: %d секунд.",
                           $duration);
            tn()->send_to_admin($msg);
            return 0;
        }

        // if external power is absent and voltage down below 11.9 volts
        // stop server and same systems
        if (!$ups_power_state && $voltage <= 12.0) {
            pnotice("voltage drop bellow 12.0v\n");

            $msg = 'Напряжение на АКБ снизилось ниже 12.0v а внешнее питание так и не появилось. ';
            $msg .= sprintf("Система проработала от бесперебойника %d секунд. ",
                            $duration);
            $msg .= 'Skynet сворачивает свою деятельсноть и отключается. До свидания.';
            tn()->send_to_admin($msg);
            $this->stop_charger();
            $this->log->info("charger stopped, run hard_reboot\n");
            run_cmd("./hard_reboot.php");
            return 0;
        }

        if (file_exists(CHARGER_DISABLE_FILE)) {
            $this->log->info("charger disabled\n");
            return 0;
        }

        $stage = NULL;
        if (file_exists(CHARGER_STAGE_FILE))
            $stage = trim(file_get_contents(CHARGER_STAGE_FILE));

        if (!$stage) {
            $this->log->info("stage is not defined, run stage 1\n");
            $this->switch_mode_to_stage1($batt_info, "start charge after reboot");
            return 0;
        }

        $cycling_state = $this->micro_cycling_state();
        $mode = $cycling_state['mode'];
        $switch_interval = $cycling_state['interval'];

        switch($stage) {
        case 'charge_stage1':
            $this->log->info("current stage %s\n", $stage);
            $this->log->info("switch_interval %d\n", $switch_interval);
            if ($switch_interval > 10 && $switch_interval < 18) {
                if ($mode == 'charge' && $current < 2.2) {
                    $msg = sprintf("Ошибка! Нет зарядного тока 2.5A. Текущий ток: %f", $current);
              //      tn()->send_to_admin($msg);
                    $this->log->err("No charge current 2.5A!\n");
                }
                if ($mode == 'discharge' && $current > -0.2 && $switch_interval > 10) {
                    $msg = sprintf("Ошибка! Нет разрядного тока 0.3A. Текущий ток: %f", $current);
                   // tn()->send_to_admin($msg);
                    $this->log->err("No discharge current 0.3A!\n");
                }
            }

            if ($mode == 'charge' && $switch_interval > 30)
                $this->switch_to_discharge();
            else if ($mode == 'discharge' && $switch_interval > 20)
                $this->switch_to_charge();

            if ($voltage <= 13.8)
                return 0;

            $this->switch_mode_to_stage2($batt_info);
            return 0;

        case 'charge_stage2':
            $this->log->info("current stage %s\n", $stage);
            $this->log->info("switch_interval %d\n", $switch_interval);
            if ($switch_interval > 10 && $switch_interval < 18) {
                if ($mode == 'charge' && $current < 0.9) {
                    $msg = sprintf("Ошибка! Нет зарядного тока 1.3A. Текущий ток: %f", $current);
                 //   tn()->send_to_admin($msg);
                    $this->log->err("No charge current 1.3A!\n");
                }
                if ($mode == 'discharge' && $current > -0.08) {
                    $msg = sprintf("Ошибка! Нет разрядного тока 0.15A. Текущий ток: %f", $current);
                   // tn()->send_to_admin($msg);
                    $this->log->err("No discharge current 0.15A!\n");
                }
            }
            if ($mode == 'charge' && $switch_interval > 20)
                $this->switch_to_discharge();
            else if ($mode == 'discharge' && $switch_interval > 30)
                $this->switch_to_charge();

            if ($voltage <= 14.4)
                return 0;

            $this->switch_mode_to_stage3($batt_info);
            return 0;

        case 'charge_stage3':
            $this->log->info("current stage %s\n", $stage);
            $this->log->info("switch_interval %d\n", $switch_interval);
            if ($switch_interval > 10 && $switch_interval < 18) {
                if ($mode == 'charge' && $current < 0.3) {
                    $msg = sprintf("Ошибка! Нет зарядного тока 0.5A. Текущий ток: %f", $current);
              //      tn()->send_to_admin($msg);
                    $this->log->err("No charge current 0.5A!\n");
                }
                if ($mode == 'discharge' && $current > -0.03) {
                    $msg = sprintf("Ошибка! Нет разрядного тока 0.05A. Текущий ток: %f", $current);
              //      tn()->send_to_admin($msg);
                    $this->log->err("No discharge current 0.5A!\n");
                }
            }
            if ($mode == 'charge' && $switch_interval > 20)
                $this->switch_to_discharge();
            else if ($mode == 'discharge' && $switch_interval > 30)
                $this->switch_to_charge();

            if ($voltage <= 15.1)
                return 0;

            $this->switch_mode_to_ready($batt_info);
            return 0;

        case 'ready':
            if ($voltage > 12.6)
                return 0;

            $this->switch_mode_to_stage4($batt_info);
            return 0;

        case 'charge_stage4':
            $this->log->info("current stage %s\n", $stage);
            $this->log->info("switch_interval %d\n", $switch_interval);
            if ($switch_interval > 10 && $switch_interval < 13) {
                if ($mode == 'charge' && $current < 0.3) {
                    $msg = sprintf("Ошибка! Нет зарядного тока 0.5A. Текущий ток: %f", $current);
                 //   tn()->send_to_admin($msg);
                    $this->log->err("No charge current 0.5A!\n");
                }
                if ($mode == 'discharge' && $current > -0.03) {
                    $msg = sprintf("Ошибка! Нет разрядного тока 0.05A. Текущий ток: %f", $current);
               //     tn()->send_to_admin($msg);
                    $this->log->err("No discharge current 0.5A!\n");
                }
            }

            if ($mode == 'charge' && $switch_interval > 20)
                $this->switch_to_discharge();
            else if ($mode == 'discharge' && $switch_interval > 30)
                $this->switch_to_charge();

            if ($voltage < 15.1)
                return 0;

            $this->switch_mode_to_ready($batt_info);
            return 0;

        default:
            $this->switch_mode_to_stage1($batt_info);
            return;
        }

        return 0;
    }
}


class Ups_tg_events implements Tg_skynet_events {
    function name() {
        return "ups";
    }

    function cmd_list() {
        return [
            ['cmd' => ['запусти проверку ибп',
                       'start test ups'],
             'method' => 'start_test'],

            ['cmd' => ['останови проверку ибп',
                       'stop test ups'],
             'method' => 'stop_test'],
            ];
    }

    function start_test($chat_id, $msg_id, $user_id, $arg, $text)
    {
        iop('ups_break_power')->up();
        tn()->send($chat_id, $msg_id, "Тестирование ИБП запущенно.");
    }

    function stop_test($chat_id, $msg_id, $user_id, $arg, $text)
    {
        if (iop('ups_break_power')->state()[0] == 0) {
            tn()->send($chat_id, $msg_id, 'Тест не был запущен');
            return;
        }
        $duration = power()->ups_duration();
        iop('ups_break_power')->down();
        $msg = "Тестирование ИБП остановленно. ";
        $msg .= sprintf("Время работы от ИБП составило %d секунд", $duration);
        tn()->send($chat_id, $msg_id, $msg);
    }
}

