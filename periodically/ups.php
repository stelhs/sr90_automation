#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'common_lib.php';
require_once 'guard_lib.php';

define("CHARGER_DISABLE_FILE", "/tmp/charger_disable");
define("STAGE_FILE", "/tmp/battery_charge_stage");
define("CHARGE_LASTTIME_FILE", "/tmp/battery_charge_lasttime");
define("DISCHARGE_LASTTIME_FILE", "/tmp/battery_discharge_lasttime");
define("LOW_BATT_VOLTAGE_FILE", "/tmp/battery_low_voltage");
define("EXT_POWER_STATE_FILE", "/run/ext_power_state");

$charger_enable_port = httpio_port(conf_ups()['charger_enable_port']);
$middle_current_enable_port = httpio_port(conf_ups()['middle_current_enable_port']);
$full_current_enable_port = httpio_port(conf_ups()['full_current_enable_port']);
$discharge_enable_port = httpio_port(conf_ups()['discharge_enable_port']);
$stop_ups_power_port = httpio_port(conf_ups()['stop_ups_power_port']);

function switch_to_discharge() {
    global $discharge_enable_port;
    $discharge_enable_port->set(1);
    file_put_contents(DISCHARGE_LASTTIME_FILE, time());
}

function switch_to_charge() {
    global $discharge_enable_port;
    global $charger_enable_port;
    $discharge_enable_port->set(0);
    $charger_enable_port->set(0);
    sleep(1);
    $charger_enable_port->set(1);
    file_put_contents(CHARGE_LASTTIME_FILE, time());
}

function set_low_current_charge()
{
    global $middle_current_enable_port, $full_current_enable_port;
    $middle_current_enable_port->set(0);
    $full_current_enable_port->set(0);
}

function set_middle_current_charge()
{
    global $middle_current_enable_port, $full_current_enable_port;
    $middle_current_enable_port->set(1);
    $full_current_enable_port->set(0);
}

function set_high_current_charge()
{
    global $middle_current_enable_port, $full_current_enable_port;
    $middle_current_enable_port->set(1);
    $full_current_enable_port->set(1);
}

function enable_charge()
{
    global $charger_enable_port;
    $charger_enable_port->set(0);
    sleep(1);
    $charger_enable_port->set(1);
}

function disable_charge()
{
    global $charger_enable_port;
    if ($charger_enable_port->get())
        switch_to_charge();
    set_low_current_charge();
    $charger_enable_port->set(0);
}

function get_micro_cycling_state()
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
    switch_to_charge();
    set_high_current_charge();
    enable_charge();
    file_put_contents(STAGE_FILE, 'charge_stage1');
    db()->insert('battery_charger', ['charger_stage' => 'stage1',
                                     'voltage' => $batt_info['voltage']]);
    $msg = sprintf('Включен заряд током 3A, напряжение на АКБ %.2f',
                   $batt_info['voltage']);
    telegram_send_admin('ups_system', ['text' => $msg]);
    db()->insert('ups_actions', ['stage' => 'charge1', 'reason' => $reason]);
}

function switch_mode_to_stage2($batt_info)
{
    switch_to_charge();
    set_middle_current_charge();
    enable_charge();
    file_put_contents(STAGE_FILE, 'charge_stage2');
    db()->insert('battery_charger', ['charger_stage' => 'stage2',
                                     'voltage' => $batt_info['voltage']]);
    $msg = sprintf('Включен заряд током 1.5A, напряжение на АКБ %.2f',
                   $batt_info['voltage']);
    telegram_send_admin('ups_system', ['text' => $msg]);
    db()->insert('ups_actions', ['stage' => 'charge2']);
}

function switch_mode_to_stage3($batt_info)
{
    switch_to_charge();
    set_low_current_charge();
    enable_charge();
    file_put_contents(STAGE_FILE, 'charge_stage3');
    db()->insert('battery_charger', ['charger_stage' => 'stage3',
                                     'voltage' => $batt_info['voltage']]);
    $msg = sprintf('Включен заряд током 0.5A, напряжение на АКБ %.2f',
                   $batt_info['voltage']);
    telegram_send_admin('ups_system', ['text' => $msg]);
    db()->insert('ups_actions', ['stage' => 'charge3']);
}

function switch_mode_to_monitoring($batt_info)
{
    file_put_contents(STAGE_FILE, 'monitoring');
    disable_charge();
    db()->insert('battery_charger', ['charger_stage' => 'monitoring',
                                     'voltage' => $batt_info['voltage']]);
    $msg = sprintf('Заряд окончен, напряжение на АКБ %.2fv',
                   $batt_info['voltage']);
    telegram_send_admin('ups_system', ['text' => $msg]);
    db()->insert('ups_actions', ['stage' => 'idle']);
}

function switch_mode_to_stage4($batt_info)
{
    switch_to_charge();
    set_low_current_charge();
    enable_charge();
    file_put_contents(STAGE_FILE, 'charge_stage4');
    db()->insert('battery_charger', ['charger_stage' => 'stage4',
                                     'voltage' => $batt_info['voltage']]);
    $msg = sprintf('Напряжение на АКБ снизилось до %.2fv, ' .
                   'включился капельный дозаряд до 14.4v',
                   $batt_info['voltage']);
    telegram_send_admin('ups_system', ['text' => $msg]);
    db()->insert('ups_actions', ['stage' => 'recharging']);
}


function stop_charger()
{
    disable_charge();
    file_put_contents(CHARGER_DISABLE_FILE, "");
}

function restart_charger()
{
    @unlink(CHARGER_DISABLE_FILE);
    @unlink(STAGE_FILE);
}


function main($argv)
{
    global $stop_ups_power_port;

// Uncomment for disable autostart
   // if (isset($argv[1]) && $argv[1] == 'auto') return;

    if (is_halt_all_systems()) {
        perror("systems is halted\n");
        return 0;
    }

    $power_states = get_power_states();
    $current_ups_power_state = $power_states['ups'];
    $current_input_power_state = $power_states['input'];
    printf("current_ups_power_state = %d\n", $current_ups_power_state);
    printf("current_input_power_state = %d\n", $current_input_power_state);

    // check for external power is absent
    @$prev_state = file_get_contents(EXT_POWER_STATE_FILE);
    if ($prev_state === FALSE) {
        file_put_contents(EXT_POWER_STATE_FILE, $current_ups_power_state);
        perror("prev_state unknown\n");
        return 0;
    }

    if ($current_ups_power_state != $prev_state) {
        printf("external power changed to %d\n", $current_ups_power_state);
        file_put_contents(EXT_POWER_STATE_FILE, $current_ups_power_state);
        if (!$current_ups_power_state) {
            if ($stop_ups_power_port->get())
                $reason = "external UPS power is off forcibly";
            else
                $reason = "external power is absent";
            db()->insert('ups_actions', ['stage' => 'discarge', 'reason' => $reason]);
            printf("stop_charger\n");
            stop_charger();
            return 0;
        }
        printf("restart_charger\n");
        restart_charger();
    }

    $batt_info = get_battery_info();
    if (!is_array($batt_info)) {
        perror("can't get baterry info\n");
        stop_charger();
        return -1;
    }

    $voltage = $batt_info['voltage'];
    printf("voltage = %f\n", $voltage);
    $current = $batt_info['current'];
    printf("current = %f\n", $current);

    if ($voltage < 11.88) {
        printf("voltage drop bellow 11.88v\n");
        @$notified = file_get_contents(LOW_BATT_VOLTAGE_FILE);
        if ((time() - $notified) > 300) {
            $msg = sprintf('Низкий заряд АКБ. Напряжение на АКБ %.2fv',
                $voltage);
            telegram_send_admin('ups_system', ['text' => $msg]);
            file_put_contents(LOW_BATT_VOLTAGE_FILE, time());
            restart_charger();
        }
    } else
        @unlink(LOW_BATT_VOLTAGE_FILE);

    // if external power is absent and voltage down below 11.9 volts
    // stop server and same systems
    if (!$current_ups_power_state && $voltage <= 11.9) {
        printf("voltage drop bellow 11.9v\n");
        @$last_ext_power_state = db()->query("SELECT UNIX_TIMESTAMP(created) as created, state ' .
                                             'FROM ext_power_log ' .
                                             'ORDER BY id DESC LIMIT 1");
        if (is_array($last_ext_power_state) &&
            $last_ext_power_state['state'] == '0') {
            $duration = time() - $last_ext_power_state['created'];
        }

        if ($current_input_power_state) {
            $stop_ups_power_port->set(0);
            printf("UPS test is success finished. Duration %d seconds\n", $duration);
            $msg = sprintf('Испытание ИБП завершено. Система проработала от АКБ %d секунд.',
                           $duration);
            telegram_send_admin('ups_system', ['text' => $msg]);
            return 0;
        }

        $msg = 'Напряжение на АКБ снизилось ниже 11.9v а внешнее питание так и не появилось. ';
        $msg .= sprintf("Система проработала от бесперебойника %d секунд. ",
                        $duration);
        $msg .= 'Skynet сворачивает свою деятельсноть и отключается. До свидания.';
        telegram_send_admin('ups_system', ['text' => $msg]);
        stop_charger();
        printf("charger stopped, run hard_reboot\n");
        run_cmd("./hard_reboot.php");
        return 0;
    }

    if (file_exists(CHARGER_DISABLE_FILE)) {
        printf("charger disabled\n");
        return 0;
    }

    @$stage = trim(file_get_contents(STAGE_FILE));
    if (!$stage) {
        printf("stage is not defined, run stage 1\n");
        switch_mode_to_stage1($batt_info, "start charge after reboot");
        return 0;
    }
    printf("current stage %s\n", $stage);

    $cycling_state = get_micro_cycling_state();
    $mode = $cycling_state['mode'];
    $switch_interval = $cycling_state['interval'];
    printf("switch_interval %d\n", $switch_interval);

    switch($stage) {
    case 'charge_stage1':
        if ($switch_interval > 10 && $switch_interval < 13) {
            if ($mode == 'charge' && $current < 3.0) {
                $msg = sprintf("Ошибка! Нет зарядного тока 3A. Текущий ток: %f", $current);
                telegram_send_admin('ups_system', ['text' => $msg]);
                perror("No charge current 3A!\n");
            }
            if ($mode == 'discharge' && $current > -0.2 && $switch_interval > 10) {
                $msg = sprintf("Ошибка! Нет разрядного тока 0.3A. Текущий ток: %f", $current);
                telegram_send_admin('ups_system', ['text' => $msg]);
                perror("No discharge current 0.3A!\n");
            }
        }

        if ($mode == 'charge' && $switch_interval > 30)
            switch_to_discharge();
        else if ($mode == 'discharge' && $switch_interval > 20)
            switch_to_charge();

        if ($voltage <= 13.8)
            return 0;

        switch_mode_to_stage2($batt_info);
        return 0;

    case 'charge_stage2':
        if ($switch_interval > 10 && $switch_interval < 13) {
            if ($mode == 'charge' && $current < 1.0) {
                $msg = sprintf("Ошибка! Нет зарядного тока 1.5A. Текущий ток: %f", $current);
                telegram_send_admin('ups_system', ['text' => $msg]);
                perror("No charge current 1.5A!\n");
            }
            if ($mode == 'discharge' && $current > -0.08) {
                $msg = sprintf("Ошибка! Нет разрядного тока 0.15A. Текущий ток: %f", $current);
                telegram_send_admin('ups_system', ['text' => $msg]);
                perror("No discharge current 0.15A!\n");
            }
        }
        if ($mode == 'charge' && $switch_interval > 20)
            switch_to_discharge();
        else if ($mode == 'discharge' && $switch_interval > 30)
            switch_to_charge();

        if ($voltage <= 14.4)
            return 0;

        switch_mode_to_stage3($batt_info);
        return 0;

    case 'charge_stage3':
        if ($switch_interval > 10 && $switch_interval < 13) {
            if ($mode == 'charge' && $current < 0.3) {
                $msg = sprintf("Ошибка! Нет зарядного тока 0.5A. Текущий ток: %f", $current);
                telegram_send_admin('ups_system', ['text' => $msg]);
                perror("No charge current 0.5A!\n");
            }
            if ($mode == 'discharge' && $current > -0.03) {
                $msg = sprintf("Ошибка! Нет разрядного тока 0.05A. Текущий ток: %f", $current);
                telegram_send_admin('ups_system', ['text' => $msg]);
                perror("No discharge current 0.5A!\n");
            }
        }
        if ($mode == 'charge' && $switch_interval > 20)
            switch_to_discharge();
        else if ($mode == 'discharge' && $switch_interval > 30)
            switch_to_charge();

        if ($voltage <= 14.9)
            return 0;

        switch_mode_to_monitoring($batt_info);
        return 0;

    case 'monitoring':
        if ($voltage > 12.7)
            return 0;

        switch_mode_to_stage4($batt_info);
        return 0;

    case 'charge_stage4':
        if ($switch_interval > 10 && $switch_interval < 13) {
            if ($mode == 'charge' && $current < 0.3) {
                $msg = sprintf("Ошибка! Нет зарядного тока 0.5A. Текущий ток: %f", $current);
                telegram_send_admin('ups_system', ['text' => $msg]);
                perror("No charge current 0.5A!\n");
            }
            if ($mode == 'discharge' && $current > -0.03) {
                $msg = sprintf("Ошибка! Нет разрядного тока 0.05A. Текущий ток: %f", $current);
                telegram_send_admin('ups_system', ['text' => $msg]);
                perror("No discharge current 0.5A!\n");
            }
        }

        if ($mode == 'charge' && $switch_interval > 20)
            switch_to_discharge();
        else if ($mode == 'discharge' && $switch_interval > 30)
            switch_to_charge();

        if ($voltage < 14.4)
            return 0;

        switch_mode_to_monitoring($batt_info);
        return 0;

    default:
        switch_mode_to_stage1($batt_info);
        return;
    }

    return 0;
}

exit(main($argv));
