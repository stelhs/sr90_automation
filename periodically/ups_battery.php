#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'common_lib.php';
require_once 'guard_lib.php';

define("VOLTAGE_FILE_QUEUE", "/tmp/ups_batt_voltage_queue");
define("CURRENT_FILE_QUEUE", "/tmp/ups_batt_current_queue");

class Queue {
    function __construct($filename, $size)
    {
        $this->size = $size;
        $this->filename = $filename;
    }

    function put($value)
    {
        @$content = file_get_contents($this->filename);
        if (!$content) {
            file_put_contents($this->filename, json_encode([$value]));
            return;
        }

        @$list = json_decode($content, true);
        if (!is_array($list)) {
            file_put_contents($this->filename, json_encode([$value]));
            return;
        }

        if (count($list) >= $this->size)
            unset($list[0]);

        $list[] = $value;
        $list = array_values($list);
        file_put_contents($this->filename, json_encode($list));
    }

    function get_val()
    {
        @$content = file_get_contents($this->filename);
        if (!$content)
            return NULL;

        @$list = json_decode($content, true);
        if (!is_array($list))
            return NULL;

        sort($list);
        return $list[ceil(count($list) / 2) - 1];
    }
}


function get_current_battery_info()
{
    if (DISABLE_HW) {
        $voltage = 12.0;
        $current = 0;
        perror("FAKE: get_battery_info() return voltage %.2fv, curent %.2fA\n", $voltage, $current);
        return ['status' => 'ok',
            'voltage' => $voltage,
            'current' => $current];
    }

    $content = file_get_contents(sprintf("http://%s:%d/battery",
        conf_io()['sbio1']['ip_addr'],
        conf_io()['sbio1']['tcp_port']));
    if (!$content)
        return ['status' => 'error',
            'error_msg' => sprintf('Can`t response from sbio1')];

    $ret_data = json_decode($content, true);
    if (!$ret_data)
        return ['status' => 'error',
            'error_msg' => sprintf('Can`t decoded battery info: %s', $content)];

    if ($ret_data['status'] != 'ok')
        return ['status' => $ret_data['status'],
            'error_msg' => $ret_data['error_msg']];

    return ['status' => 'ok',
        'voltage' => $ret_data['voltage'],
        'current' => $ret_data['current']];
}



function main($argv)
{
    $batt_info = get_current_battery_info();
    if (!is_array($batt_info))
        return -1;

    if ($batt_info['status'] != 'ok') {
        telegram_send_admin('ups_system',
            ['error' => sprintf('get_battery_info() return %s, sbio1 go to reboot',
                $batt_info['error_msg'])]);
        @unlink(UPS_BATT_VOLTAGE_FILE);
        @unlink(UPS_BATT_CURRENT_FILE);
        reboot_sbio('sbio1');
        return -1;
    }

    $voltage_queue = new Queue(VOLTAGE_FILE_QUEUE, 5);
    $current_queue = new Queue(CURRENT_FILE_QUEUE, 5);

    $voltage_queue->put($batt_info['voltage']);
    $current_queue->put($batt_info['current']);
    $filtred_voltage = $voltage_queue->get_val();
    $filtred_current = $current_queue->get_val();

    @$prev_voltage = file_get_contents(UPS_BATT_VOLTAGE_FILE);
    @$prev_current = file_get_contents(UPS_BATT_CURRENT_FILE);
    if ($filtred_voltage == $prev_voltage &&
        $filtred_current == $prev_current) {
        return;
    }

    file_put_contents(UPS_BATT_VOLTAGE_FILE, $filtred_voltage);
    file_put_contents(UPS_BATT_CURRENT_FILE, $filtred_current);
    db()->insert('ups_battery',
                 ['voltage' => $filtred_voltage,
                  'current' => $filtred_current]);
}

exit(main($argv));
