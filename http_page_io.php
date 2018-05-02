#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once 'common_lib.php';
require_once 'usio_lib.php';

function main($argv)
{
    if ((!isset($argv[1]) || (!isset($argv[2])))) {
        perror("Incorrect arguments\n");
        return -EINVAL;
    }

    $mode = strtolower(trim($argv[2]));
    parse_str($argv[1], $data);

    switch ($mode) {
    case 'relay_set':
        if ((!isset($data['port'])) || (!isset($data['state'])))
            return json_encode(['status' => 'error']);
        $port = strtolower(trim($data['port']));
        $state = strtolower(trim($data['state']));
        $rc = usio()->relay_set_state($port, $state);
        if ($rc)
            return $rc;
        break;

    case 'relay_get':
        if (!isset($data['port'])) {
            perror("port not set\n");
            return -EINVAL;
        }
        $port = strtolower(trim($data['port']));
        $state = usio()->relay_get_state($port);
        if ($state < 0) {
            perror("Can't get relay state\n");
            return $state;
        }
        echo json_encode(['state' => $state]);
        break;

    case 'input_get':
        if (!isset($data['port'])) {
            perror("port not set\n");
            return -EINVAL;
        }
        $port = strtolower(trim($data['port']));
        $state = usio()->input_get_state($port);
        if ($state < 0)
            return $rc;

        echo json_encode(['state' => $state]);
        break;
    }
}

exit(main($argv));