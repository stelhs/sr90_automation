#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'common_lib.php';
require_once 'guard_lib.php';
require_once 'telegram_api.php';

define("CHARGER_DISABLE_FILE", "/tmp/charger_disable");
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
    file_put_contents(CHARGER_STAGE_FILE, 'charge_stage1');
    $msg = sprintf('Включен заряд током 3A, напряжение на АКБ %.2f',
                   $batt_info['voltage']);
    telegram_send_msg_admin($msg);
    db()->insert('ups_actions', ['stage' => 'charge1', 'reason' => $reason]);
}

function switch_mode_to_stage2($batt_info)
{
    switch_to_charge();
    set_middle_current_charge();
    enable_charge();
    file_put_contents(CHARGER_STAGE_FILE, 'charge_stage2');
    $msg = sprintf('Включен заряд током 1.5A, напряжение на АКБ %.2f',
                   $batt_info['voltage']);
    telegram_send_msg_admin($msg);
    db()->insert('ups_actions', ['stage' => 'charge2']);
}

function switch_mode_to_stage3($batt_info)
{
    switch_to_charge();
    set_low_current_charge();
    enable_charge();
    file_put_contents(CHARGER_STAGE_FILE, 'charge_stage3');
    $msg = sprintf('Включен заряд током 0.5A, напряжение на АКБ %.2f',
                   $batt_info['voltage']);
    telegram_send_msg_admin($msg);
    db()->insert('ups_actions', ['stage' => 'charge3']);
}

function switch_mode_to_ready($batt_info)
{
    file_put_contents(CHARGER_STAGE_FILE, 'ready');
    disable_charge();
    $msg = sprintf('Заряд окончен, напряжение на АКБ %.2fv',
                   $batt_info['voltage']);
    telegram_send_msg_admin($msg);
    db()->insert('ups_actions', ['stage' => 'idle']);
}

function switch_mode_to_stage4($batt_info)
{
    set_low_current_charge();
    switch_to_charge();
    file_put_contents(CHARGER_STAGE_FILE, 'charge_stage4');
    $msg = sprintf('Напряжение на АКБ снизилось до %.2fv, ' .
                   'включился капельный дозаряд до 14.4v',
                   $batt_info['voltage']);
    telegram_send_msg_admin($msg);
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
    @unlink(CHARGER_STAGE_FILE);
}


function main($argv)
{
    global $stop_ups_power_port;

// Uncomment for disable autostart
//   if (isset($argv[1]) && $argv[1] == 'auto'){ stop_charger(); return; }

    if (is_halt_all_systems()) {
        perror("systems is halted\n");
        return 0;
    }

    $power_states = get_power_states();
    $ups_power_state = isset($power_states['ups']) ? $power_states['ups'] : -1;
    $input_power_state = isset($power_states['input']) ? $power_states['input'] : -1;
    printf("current_ups_power_state = %d\n", $ups_power_state);
    printf("current_input_power_state = %d\n", $input_power_state);

    if ($ups_power_state < 0 || $input_power_state < 0) {
        perror("incorrect ups_power_state or input_power_state\n");
        return -1;
    }

    // check for external power is absent
    @$prev_state = file_get_contents(EXT_POWER_STATE_FILE);
    if ($prev_state === FALSE) {
        file_put_contents(EXT_POWER_STATE_FILE, $ups_power_state);
        perror("prev_state unknown\n");
        return 0;
    }

    if ($ups_power_state != $prev_state) {
        printf("external power changed to %d\n", $ups_power_state);
        file_put_contents(EXT_POWER_STATE_FILE, $ups_power_state);
        if (!$ups_power_state) {
            if ($stop_ups_power_port->get())
                $reason = "external UPS power is off forcibly";
            else
                $reason = "external power is absent";
            db()->insert('ups_actions', ['stage' => 'discarge', 'reason' => $reason]);
            telegram_send_msg_admin(sprintf("остановка зарядки из за ups_power_state, ups_power_state = %d, prev_state = %d",
                                            $ups_power_state, $prev_state));
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
        telegram_send_msg_admin("Ошибка получения инфорамции о АКБ");
        return -1;
    }

    $voltage = $batt_info['voltage'];
    printf("voltage = %f\n", $voltage);
    $current = $batt_info['current'];
    printf("current = %f\n", $current);

    if ($voltage <= 0)
        return -1;

    if ($voltage < 11.88) {
        printf("voltage drop bellow 11.88v\n");
        @$notified = file_get_contents(LOW_BATT_VOLTAGE_FILE);
        if ((time() - $notified) > 300) {
            $msg = sprintf('Низкий заряд АКБ. Напряжение на АКБ %.2fv',
                $voltage);
            telegram_send_msg_admin($msg);
            file_put_contents(LOW_BATT_VOLTAGE_FILE, time());
            restart_charger();
        }
    } else
        @unlink(LOW_BATT_VOLTAGE_FILE);

    // if external power is absent and voltage down below 11.9 volts
    // stop server and same systems
    if (!$ups_power_state && $voltage <= 12.0) {
        printf("voltage drop bellow 12.0v\n");
        $duration = get_last_ups_duration();

        if ($input_power_state) {
            $stop_ups_power_port->set(0);
            printf("UPS test is success finished. Duration %d seconds\n", $duration);
            $msg = sprintf("Испытание ИБП завершено.\n" .
                           "Система проработала от АКБ: %d секунд.",
                           $duration);
            telegram_send_msg_admin($msg);
            return 0;
        }

        $msg = 'Напряжение на АКБ снизилось ниже 12.0v а внешнее питание так и не появилось. ';
        $msg .= sprintf("Система проработала от бесперебойника %d секунд. ",
                        $duration);
        $msg .= 'Skynet сворачивает свою деятельсноть и отключается. До свидания.';
        telegram_send_msg_admin($msg);
        stop_charger();
        printf("charger stopped, run hard_reboot\n");
        run_cmd("./hard_reboot.php");
        return 0;
    }

    if (file_exists(CHARGER_DISABLE_FILE)) {
        printf("charger disabled\n");
        return 0;
    }

    @$stage = trim(file_get_contents(CHARGER_STAGE_FILE));
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
        if ($switch_interval > 10 && $switch_interval < 18) {
            if ($mode == 'charge' && $current < 2.2) {
                $msg = sprintf("Ошибка! Нет зарядного тока 2.5A. Текущий ток: %f", $current);
                telegram_send_msg_admin($msg);
                perror("No charge current 2.5A!\n");
            }
            if ($mode == 'discharge' && $current > -0.2 && $switch_interval > 10) {
                $msg = sprintf("Ошибка! Нет разрядного тока 0.3A. Текущий ток: %f", $current);
                telegram_send_msg_admin($msg);
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
        if ($switch_interval > 10 && $switch_interval < 18) {
            if ($mode == 'charge' && $current < 0.9) {
                $msg = sprintf("Ошибка! Нет зарядного тока 1.3A. Текущий ток: %f", $current);
                telegram_send_msg_admin($msg);
                perror("No charge current 1.3A!\n");
            }
            if ($mode == 'discharge' && $current > -0.08) {
                $msg = sprintf("Ошибка! Нет разрядного тока 0.15A. Текущий ток: %f", $current);
                telegram_send_msg_admin($msg);
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
        if ($switch_interval > 10 && $switch_interval < 18) {
            if ($mode == 'charge' && $current < 0.3) {
                $msg = sprintf("Ошибка! Нет зарядного тока 0.5A. Текущий ток: %f", $current);
                telegram_send_msg_admin($msg);
                perror("No charge current 0.5A!\n");
            }
            if ($mode == 'discharge' && $current > -0.03) {
                $msg = sprintf("Ошибка! Нет разрядного тока 0.05A. Текущий ток: %f", $current);
                telegram_send_msg_admin($msg);
                perror("No discharge current 0.5A!\n");
            }
        }
        if ($mode == 'charge' && $switch_interval > 20)
            switch_to_discharge();
        else if ($mode == 'discharge' && $switch_interval > 30)
            switch_to_charge();

        if ($voltage <= 15.1)
            return 0;

        switch_mode_to_ready($batt_info);
        return 0;

    case 'ready':
        if ($voltage > 12.7)
            return 0;

        switch_mode_to_stage4($batt_info);
        return 0;

    case 'charge_stage4':
        if ($switch_interval > 10 && $switch_interval < 13) {
            if ($mode == 'charge' && $current < 0.3) {
                $msg = sprintf("Ошибка! Нет зарядного тока 0.5A. Текущий ток: %f", $current);
                telegram_send_msg_admin($msg);
                perror("No charge current 0.5A!\n");
            }
            if ($mode == 'discharge' && $current > -0.03) {
                $msg = sprintf("Ошибка! Нет разрядного тока 0.05A. Текущий ток: %f", $current);
                telegram_send_msg_admin($msg);
                perror("No discharge current 0.5A!\n");
            }
        }

        if ($mode == 'charge' && $switch_interval > 20)
            switch_to_discharge();
        else if ($mode == 'discharge' && $switch_interval > 30)
            switch_to_charge();

        if ($voltage < 15.1)
            return 0;

        switch_mode_to_ready($batt_info);
        return 0;

    default:
        switch_mode_to_stage1($batt_info);
        return;
    }

    return 0;
}

exit(main($argv));
