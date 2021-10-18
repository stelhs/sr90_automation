#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once 'common_lib.php';

function main($argv)
{
    $stat = [];
    @$content = file_get_contents(TEMPERATURES_FILE);
    if ($content)
        $stat['termo_sensors'] = json_decode($content, 1);

    $stat['io_states'] = io_states();
    $stat['batt_info'] = battery_info();
    echo json_encode($stat);
}

exit(main($argv));
