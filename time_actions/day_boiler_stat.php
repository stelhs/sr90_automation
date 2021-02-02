#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'boiler_api.php';
require_once 'common_lib.php';


function main($argv) {

    $stat = boiler_stat();

    $row = db()->query('select avg(`temperature`) as t from termo_sensors_log '.
                       'where sensor_name = "28-00000a882264" and ' .
                           'created > (now() - interval 1 day)');
    $outside_t = $row['t'];

    db()->insert('boiler_statistics',
                 ['burning_time' => $stat['total_burning_time'],
                  'fuel_consumption' => ($stat['total_fuel_consumption'] * 1000),
                  'ignition_counter' => $stat['ignition_counter'],
                  'return_water_t' => $stat['overage_return_water_t'],
                  'room_t' => $stat['overage_room_t'],
                  'outside_t' => $outside_t]);

    boiler_reset_stat();
    $msg = sprintf("Отчёт по котлу за прошедшие сутки: \n" .
                   "Время горения: %s\n" .
                   "Количество запусков: %d\n" .
                   "Усреднённая температура в мастерской (за сутки): %.1f градусов\n" .
                   "Усреднённая температура в чугунных радиаторах (за сутки): %.1f градусов\n" .
                   "Усреднённая температура на улице (за сутки): %.1f градусов\n" .
                   "Израсходованно дизельного топлива: %.1f литров",
                   $stat['total_burning_time_text'], $stat['ignition_counter'],
                   $stat['overage_room_t'], $stat['overage_return_water_t'],
                   $outside_t, $stat['total_fuel_consumption']);
    telegram()->send_message("-1001397133801", $msg);
    return 0;
}

exit(main($argv));
