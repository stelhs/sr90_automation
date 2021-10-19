#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';

require_once 'common_lib.php';
require_once 'guard_api.php';
require_once 'well_pump_api.php';
require_once 'power_api.php';
require_once 'gates_api.php';


function main($argv)
{
    $log = new Plog('sr90:http_ioserver');
    $debug_mode = false;
    if (!isset($argv[1]))
        return json_encode(['status' => 'error']);

    if ($argv[1] == 'debug') {
        $debug_mode = true;
        $io_name = strtolower(trim($argv[2]));
        $port_num = strtolower(trim($argv[3]));
        $state = strtolower(trim($argv[4]));
    } else {
        parse_str($argv[1], $data);

        if ((!isset($data['io'])) || (!isset($data['port'])) || (!isset($data['state']))) {
            $log->err("Incorrect arguments\n");
            return -EINVAL;
        }

        $io_name = strtolower(trim($data['io']));
        $port_num = strtolower(trim($data['port']));
        $state = strtolower(trim($data['state']));
    }

    $pname = port_name_by_addr($io_name, 'in', $port_num);

    if (!$pname) {
        $log->err("port %s.in.%d is not registred\n", $io_name, $port_num);
        return -EINVAL;
    }

    if ($state < 0 || $state > 1) {
        $log->err("Incorrect port state %d. Port state must be 0 or 1\n", $state);
        return -EINVAL;
    }

    trig_io_event($pname, $state);
    return json_encode(['status' => 'ok']);
}

exit(main($argv));