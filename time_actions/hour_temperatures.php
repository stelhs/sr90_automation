#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'guard_lib.php';
require_once 'common_lib.php';

function main($argv) {
    $termo_sensors = get_termosensors_stat();

    $temperature_stat = [];
    foreach ($termo_sensors as $sensor) {
        $temperature_stat[$sensor['sensor_name']] = $sensor;

        $query = sprintf("SELECT created, temperature " .
            "FROM `termo_sensors_log` " .
            "WHERE sensor_name = '%s' " .
            "AND created > (now() - INTERVAL 1 DAY) " .
            "ORDER BY temperature ASC LIMIT 1",
            $sensor['sensor_name']);
        $row = db()->query($query);
        $temperature_stat[$sensor['sensor_name']]['min'] = $row;

        $query = sprintf("SELECT created, temperature " .
            "FROM `termo_sensors_log` " .
            "WHERE sensor_name = '%s' " .
            "AND created > (now() - INTERVAL 1 DAY) " .
            "ORDER BY temperature DESC LIMIT 1",
            $sensor['sensor_name']);
        $row = db()->query($query);
        $temperature_stat[$sensor['sensor_name']]['max'] = $row;

        $query = sprintf("SELECT avg(temperature) as temperature " .
            "FROM `termo_sensors_log` " .
            "WHERE sensor_name = '%s' " .
            "AND created > (now() - INTERVAL 1 DAY)",
            $sensor['sensor_name']);
        $row = db()->query($query);
        $temperature_stat[$sensor['sensor_name']]['avg'] = $row['temperature'];
    }

    file_put_contents(TEMPERATURES_FILE, json_encode($temperature_stat));
    return 0;
}

exit(main($argv));
