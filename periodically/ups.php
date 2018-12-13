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
define("PREV_VOLTAGE_FILE", "/tmp/prev_voltage");

$charger_enable_port = httpio_port(conf_ups()['charger_enable_port']);
$middle_current_enable_port = httpio_port(conf_ups()['middle_current_enable_port']);
$full_current_enable_port = httpio_port(conf_ups()['full_current_enable_port']);
$discharge_enable_port = httpio_port(conf_ups()['discharge_enable_port']);
$external_power_port = httpio_port(conf_ups()['external_power_port']);
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


function switch_mode_to_stage1($batt_info)
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
}


function stop_charger()
{
    disable_charge();
    file_put_contents(CHARGER_DISABLE_FILE);
}

function restart_charger()
{
    unlink(CHARGER_DISABLE_FILE);
    unlink(STAGE_FILE);
}

function main($argv)
{
    global $external_power_port;
    global $disable_ups_output_port;
    global $disable_ups_power_port;
    global $stop_ups_power_port;

// Uncomment for disable autostart
   // if (isset($argv[1]) && $argv[1] == 'auto') return;

    if (is_halt_all_systems())
        return 0;

    $current_ext_power_state = $external_power_port->get();
    $current_ext_ups_power_state = $stop_ups_power_port->get();

    // check for external power is absent
    @$prev_state = file_get_contents(EXT_POWER_STATE_FILE);
    if (!$prev_state) {
        file_put_contents(EXT_POWER_STATE_FILE, $current_ext_power_state);
        return 0;
    }

    if ($current_ext_power_state != $prev_state) {
        file_put_contents(EXT_POWER_STATE_FILE, $current_ext_power_state);

        if ($current_ext_power_state)
            $msg = 'Внешнее питание восстановлено';
        else
            $msg = 'Отключено внешнее питание';

        telegram_send_admin('ups_system', ['text' => $msg]);

        db()->insert('ext_power_log',
                     ['state' => ($current_ext_power_state ? 'on' : 'off')]);

        if (!$current_ext_power_state) {
            stop_charger();
            restart_charger();
            return 0;
        }
        restart_charger();
    }

    if (file_exists(CHARGER_DISABLE_FILE))
        return 0;

    $batt_info = get_battery_info();
    if (!is_array($batt_info)) {
        stop_charger();
        return -1;
    }

    if ($batt_info['status'] != 'ok') {
        telegram_send_admin('ups_system',
                           ['error' => sprintf('get_battery_info() return %s, sbio1 go to reboot',
                                               $batt_info['error_msg'])]);
        reboot_sbio('sbio1');
        stop_charger();
        return -1;
    }

    $voltage = $batt_info['voltage'];
    @$prev_voltage = (float)file_get_contents(PREV_VOLTAGE_FILE);
    if (!$prev_voltage) {
        file_put_contents(PREV_VOLTAGE_FILE, $voltage);
        return 0;
    }

    // drop purge ADC values
    if (abs($voltage - $prev_voltage) > 3)
        return 0;

    file_put_contents(PREV_VOLTAGE_FILE, $voltage);

    if ($voltage < 11.88) {
        @$notified = file_get_contents(LOW_BATT_VOLTAGE_FILE);
        if (!$notified) {
            $msg = sprintf('Низкий заряд АКБ. Напряжение на АКБ %.2fv',
                $voltage);
            telegram_send_admin('ups_system', ['text' => $msg]);
            file_put_contents(LOW_BATT_VOLTAGE_FILE, time());
        }
    } else
        @unlink(LOW_BATT_VOLTAGE_FILE);

    // if external power is absent and voltage down below 12 volts
    // stop server and same systems
    if ((!$current_ext_power_state || $current_ext_ups_power_state) && $voltage <= 11.9) {
        $msg = 'Напряжение на АКБ снизилось ниже 11.9v а внешнее питание так и не появилось. ';

        @$last_ext_power_state = db()->query("SELECT * FROM ext_power_log ORDER BY id DESC LIMIT 1");
        if (is_array($last_ext_power_state) && $last_ext_power_state['state'] == 'off')
            $msg .= sprintf("Система проработала от бесперебойника %d секунд. ",
                            time() - $last_ext_power_state['created']);

        $msg .= 'Skynet сворачивает свою деятельсноть и отключается. До свидания.';
        telegram_send_admin('ups_system', ['text' => $msg]);
        restart_charger();
        run_cmd("./hard_reboot.php");
        return 0;
    }

    if (!$current_ext_power_state || $current_ext_ups_power_state) {
        disable_charge();
        return;
    }

    @$stage = file_get_contents(STAGE_FILE);
    $stage = trim($stage);
    if (!$stage) {
        switch_mode_to_stage1($batt_info);
        return 0;
    }

    $cycling_state = get_micro_cycling_state();
    $mode = $cycling_state['mode'];
    $switch_interval = $cycling_state['interval'];

    switch($stage) {
    case 'charge_stage1':
        if ($mode == 'charge' && $switch_interval > 30)
            switch_to_discharge();
        else if ($mode == 'discharge' && $switch_interval > 20)
            switch_to_charge();

        if ($voltage <= 13.8)
            return 0;

        switch_mode_to_stage2($batt_info);
        return 0;

    case 'charge_stage2':
        if ($mode == 'charge' && $switch_interval > 20)
            switch_to_discharge();
        else if ($mode == 'discharge' && $switch_interval > 30)
            switch_to_charge();

        if ($voltage <= 14.4)
            return 0;

        switch_mode_to_stage3($batt_info);
        return 0;

    case 'charge_stage3':
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