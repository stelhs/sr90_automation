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
                  'fuel_consumption' => $stat['total_fuel_consumption'],
                  'ignition_counter' => $stat['ignition_counter'],
                  'return_water_t' => $stat['overage_return_water_t'],
                  'room_t' => $stat['overage_room_t'],
                  'outside_t' => $outside_t]);

    boiler_reset_stat();
    return 0;
}

exit(main($argv));
