#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once 'common_lib.php';

function main($argv)
{
    echo json_encode(['termo_sensors' => get_termosensors_stat()]);
}

exit(main($argv));
