<?php

require_once 'config.php';
require_once 'board_io_api.php';
require_once 'common_lib.php';

define("UPS_BATT_VOLTAGE_FILE", "/tmp/ups_batt_voltage");
define("UPS_BATT_CURRENT_FILE", "/tmp/ups_batt_current");
define("CHARGER_STAGE_FILE", "/tmp/battery_charge_stage");

define("VOLTAGE_FILE_QUEUE", "/tmp/ups_batt_voltage_queue");
define("CURRENT_FILE_QUEUE", "/tmp/ups_batt_current_queue");

define("CHARGER_DISABLE_FILE", "/tmp/charger_disable");
define("CHARGE_LASTTIME_FILE", "/tmp/battery_charge_lasttime");
define("DISCHARGE_LASTTIME_FILE", "/tmp/battery_discharge_lasttime");
define("LOW_BATT_VOLTAGE_FILE", "/tmp/battery_low_voltage");
define("EXT_POWER_STATE_FILE", "/run/ext_power_state");


class Ext_power_io_handler implements IO_handler {
    function name()
    {
        return "power_monitor";
    }

    function trigger_ports() {
        return ['ext_power' => 2,
                'ups_220vac' => 2,
                'ups_14vdc' => 0,
                'ups_250vdc' => 0];
    }

    function event_handler($pname, $state)
    {
        if ($pname == 'ext_power') {
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
                return 0;
            }
            tn()->send_to_admin($msg);
            db()->insert('ext_power_log',
                         ['state' => $state,
                          'type' => 'input']);
            return 0;
        }

        if ($pname == 'ups_220vac') {
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
                    return 0;
            }

            tn()->send_to_admin($msg);
            db()->insert('ext_power_log',
                         ['state' => $state,
                          'type' => 'ups']);
            return 0;
        }

        if ($pname == 'ups_250vdc') {
            $msg = 'Ошибка ИБП: отсутсвует выходное напряжение 250vdc';
            tn()->send_to_admin($msg);
            return 0;
        }

        if ($pname == 'ups_14vdc') {
            $msg = 'Ошибка ИБП: отсутсвует выходное напряжение 14vdc';
            tn()->send_to_admin($msg);
            return 0;
        }
        return 0;
    }
}

function battery_info()
{
    @$voltage = trim(file_get_contents(UPS_BATT_VOLTAGE_FILE));
    if ($voltage === FALSE)
        return null;

    @$current = trim(file_get_contents(UPS_BATT_CURRENT_FILE));
    if ($current === FALSE)
        return null;

    return ['voltage' => $voltage,
            'current' => $current];
}


function power_state()
{
    $power['input'] = iop('ext_power')->state();
    $power['ups'] = iop('ups_220vac')->state();
    return $power;
}

function ups_state()
{
    $stat = [];
    $stat['vdc_out_state'] = iop('ups_250vdc')->state();
    $stat['standby_state'] = iop('ups_14vdc')->state();
    @$stat['charger_state'] = file_get_contents(CHARGER_STAGE_FILE);
    return $stat;
}


/**
 * Get duration between UPS power loss and UPS power resume
 */
function last_ups_duration()
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


class Ups_batterry_periodically implements Periodically_events {
    function name()
    {
        return "ups_battery";
    }

    function interval()
    {
        return 1;
    }

    function batt_info()
    {
        if (DISABLE_HW) {
            $voltage = 12.0;
            $current = 0;
            perror("FAKE: _battery_info() return voltage %.2fv, curent %.2fA\n",
                    $voltage, $current);
            return ['status' => 'ok',
                    'voltage' => $voltage,
                    'current' => $current];
        }

        $content = file_get_contents(sprintf("http://%s:%d/battery",
            conf_io()['sbio1']['ip_addr'],
            conf_io()['sbio1']['tcp_port']));
        if (!$content)
            return ['status' => 'error',
                    'error' => "no_io",
                    'error_msg' => sprintf('Can`t response from sbio1')];

        $ret_data = json_decode($content, true);
        if (!$ret_data)
            return ['status' => 'error',
                    'error' => "no_batt",
                    'error_msg' => sprintf('Can`t decoded battery info: %s', $content)];

        if ($ret_data['status'] != 'ok')
            return ['status' => $ret_data['status'],
                    'error_msg' => $ret_data['error_msg']];

        return ['status' => 'ok',
                'voltage' => $ret_data['voltage'],
                'current' => $ret_data['current']];
    }


    function is_around($probe_val, $reference_val, $tolerance)
    {
        if (($reference_val - $probe_val) > $tolerance)
            return False;

        if (($probe_val - $reference_val) > $tolerance)
            return False;

        return True;
    }

    function do() {
        $batt_info = $this->batt_info();
        if (!is_array($batt_info))
            return -1;

        if ($batt_info['status'] != 'ok') {
            if (isset($batt_info['error'])) {
                if ($batt_info['error'] == 'no_io')
                    return -1;

                $msg = sprintf("Error: battery_info() return %s, sbio1 go to reboot",
                    $batt_info['error_msg']);
                tn()->send_to_admin($msg);
                unlink_safe(UPS_BATT_VOLTAGE_FILE);
                unlink_safe(UPS_BATT_CURRENT_FILE);
                reboot_sbio('sbio1');
            }
            tn()->send_to_admin("Ошибка АКБ");
            return -1;
        }

        $voltage_queue = new Queue_file(VOLTAGE_FILE_QUEUE, 5);
        $current_queue = new Queue_file(CURRENT_FILE_QUEUE, 5);

        $voltage_queue->put($batt_info['voltage']);
        $current_queue->put($batt_info['current']);
        $filtred_voltage = $voltage_queue->get_val();
        $filtred_current = $current_queue->get_val();

        @$prev_voltage = file_get_contents(UPS_BATT_VOLTAGE_FILE);
        @$prev_current = file_get_contents(UPS_BATT_CURRENT_FILE);
        if ($filtred_voltage == $prev_voltage &&
                $this->is_around($filtred_current, $prev_current, 0.03))
            return;

        file_put_contents(UPS_BATT_VOLTAGE_FILE, $filtred_voltage);
        file_put_contents(UPS_BATT_CURRENT_FILE, $filtred_current);
        db()->insert('ups_battery',
                     ['voltage' => $filtred_voltage,
                      'current' => $filtred_current]);
    }
}


class Ups_periodically implements Periodically_events {
    function __construct()
    {
        $this->log = new Plog('sr90:ups');
    }

    function name()
    {
        return "ups";
    }

    function interval()
    {
        return 1;
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
        iop('charger_1.5A')->down();
        iop('charger_3A')->down();
    }

    function set_middle_current_charge()
    {
        iop('charger_1.5A')->up();
        iop('charger_3A')->down();
    }

    function set_high_current_charge()
    {
        iop('charger_1.5A')->up();
        iop('charger_3A')->up();
    }

    function enable_charge()
    {
        iop('charger_en')->down();
        sleep(1);
        iop('charger_en')->up();
    }

    function disable_charge()
    {
        if (iop('charger_en')->state())
            $this->switch_to_charge();
        $this->set_low_current_charge();
        iop('charger_en')->down();
    }

    function micro_cycling_state()
    {
        @$charge_lasttime = file_get_contents(CHARGE_LASTTIME_FILE);
        @$discharge_lasttime = file_get_contents(DISCHARGE_LASTTIME_FILE);
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
        if (is_halt_all_systems()) {
            perror("systems is halted\n");
            return 0;
        }

        $power_states = power_state();
        $ups_power_state = isset($power_states['ups']) ? $power_states['ups'] : -1;
        $input_power_state = isset($power_states['input']) ? $power_states['input'] : -1;
        pnotice("current_ups_power_state = %d\n", $ups_power_state);
        pnotice("current_input_power_state = %d\n", $input_power_state);

        if ($ups_power_state < 0 || $input_power_state < 0) {
            $this->log->err("incorrect ups_power_state or input_power_state\n");
            return -1;
        }

        // check for external power is absent
        @$prev_state = file_get_contents(EXT_POWER_STATE_FILE);
        if ($prev_state === FALSE) {
            file_put_contents(EXT_POWER_STATE_FILE, $ups_power_state);
            $this->log->err("prev_state unknown\n");
            return 0;
        }

        if ($ups_power_state != $prev_state) {
            $this->log->info("external power changed to %d\n", $ups_power_state);
            file_put_contents(EXT_POWER_STATE_FILE, $ups_power_state);
            if (!$ups_power_state) {
                if (iop('ups_break_power')->state())
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

        $batt_info = battery_info();
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
            @$notified = file_get_contents(LOW_BATT_VOLTAGE_FILE);
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
            $duration = last_ups_duration();
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

        @$stage = trim(file_get_contents(CHARGER_STAGE_FILE));
        if (!$stage) {
            $this->log->info("stage is not defined, run stage 1\n");
            $this->switch_mode_to_stage1($batt_info, "start charge after reboot");
            return 0;
        }

        $this->log->info("current stage %s\n", $stage);

        $cycling_state = $this->micro_cycling_state();
        $mode = $cycling_state['mode'];
        $switch_interval = $cycling_state['interval'];
        $this->log->info("switch_interval %d\n", $switch_interval);

        switch($stage) {
        case 'charge_stage1':
            if ($switch_interval > 10 && $switch_interval < 18) {
                if ($mode == 'charge' && $current < 2.2) {
                    $msg = sprintf("Ошибка! Нет зарядного тока 2.5A. Текущий ток: %f", $current);
                    tn()->send_to_admin($msg);
                    $this->log->err("No charge current 2.5A!\n");
                }
                if ($mode == 'discharge' && $current > -0.2 && $switch_interval > 10) {
                    $msg = sprintf("Ошибка! Нет разрядного тока 0.3A. Текущий ток: %f", $current);
                    tn()->send_to_admin($msg);
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
            if ($switch_interval > 10 && $switch_interval < 18) {
                if ($mode == 'charge' && $current < 0.9) {
                    $msg = sprintf("Ошибка! Нет зарядного тока 1.3A. Текущий ток: %f", $current);
                    tn()->send_to_admin($msg);
                    $this->log->err("No charge current 1.3A!\n");
                }
                if ($mode == 'discharge' && $current > -0.08) {
                    $msg = sprintf("Ошибка! Нет разрядного тока 0.15A. Текущий ток: %f", $current);
                    tn()->send_to_admin($msg);
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
            if ($switch_interval > 10 && $switch_interval < 18) {
                if ($mode == 'charge' && $current < 0.3) {
                    $msg = sprintf("Ошибка! Нет зарядного тока 0.5A. Текущий ток: %f", $current);
                    tn()->send_to_admin($msg);
                    $this->log->err("No charge current 0.5A!\n");
                }
                if ($mode == 'discharge' && $current > -0.03) {
                    $msg = sprintf("Ошибка! Нет разрядного тока 0.05A. Текущий ток: %f", $current);
                    tn()->send_to_admin($msg);
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
            if ($switch_interval > 10 && $switch_interval < 13) {
                if ($mode == 'charge' && $current < 0.3) {
                    $msg = sprintf("Ошибка! Нет зарядного тока 0.5A. Текущий ток: %f", $current);
                    tn()->send_to_admin($msg);
                    $this->log->err("No charge current 0.5A!\n");
                }
                if ($mode == 'discharge' && $current > -0.03) {
                    $msg = sprintf("Ошибка! Нет разрядного тока 0.05A. Текущий ток: %f", $current);
                    tn()->send_to_admin($msg);
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
    function name()
    {
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
        if (iop('ups_break_power')->state() == 0) {
            tn()->send($chat_id, $msg_id, 'Тест не был запущен');
            return;
        }
        $duration = last_ups_duration();
        iop('ups_break_power')->down();
        $msg = "Тестирование ИБП остановленно. ";
        $msg .= sprintf("Время работы от ИБП составило %d секунд", $duration);
        tn()->send($chat_id, $msg_id, $msg);
    }
}

