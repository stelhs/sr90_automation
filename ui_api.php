<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';

require_once 'common_lib.php';
require_once 'config.php';

class Ui {
    function __construct() {
        $this->log = new Plog("sr90:ui");
    }

    function request_post($url, $args)
    {
        $http_request = sprintf("http://%s:%d%s",
                                conf_ui()['ip'],
                                conf_ui()['port'],
                                $url);

        $options = ['http' => ['header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                               'method'  => 'POST',
                               'content' => json_encode($args)]];
        $context  = stream_context_create($options);

        $content = file_get_contents_safe($http_request, false, $context);
        if (!$content) {
            $this->log->err("Null bytes received from addr: %s", $http_request);
            return ['status' => 'error',
                    'reason' => 'connection erro  r'];
        }

        $ret_data = json_decode($content, true);
        if (!$ret_data) {
            $this->log->err("Can't decode JSON from addr: %s", $http_request);
            return ['status' => 'error',
                    'reason' => 'json decode error'];
        }
        return $ret_data;
    }

    function notify($subsystem, $type, $data) {
        $ret = $this->request_post('/send_event',
                                   ['subsytem' => $subsystem,
                                    'type' => $type,
                                    'data' => $data]);

        if (!isset($ret['status'])) {
            $this->log->err('Can`t notify UI: field "status" is absent');
            return False;
        }

        if ($ret['status'] != 'ok') {
            $this->log->err('Can`t notify UI: %s', $ret['reason']);
            return False;
        }

        return True;
    }
}

function ui()
{
    static $ui = NULL;
    if (!$ui)
        $ui = new Ui;

    return $ui;
}


class Ui_io_handler implements Http_handler {
    function name() {
        return "ui";
    }

    function requests() {
        return ['/ui/boiler/get_fuel_consumption_stat' => ['method' => 'GET',
                                                           'handler' => 'boiler_fuel_consumption_stat',
                            ]];
    }

    function __construct() {
        $this->log = new Plog('sr90:Stat_io_handler');
    }

    function boiler_fuel_consumption_stat($args, $from, $request)
    {
        if (!isset($args['year'])) {
            return json_encode(['status' => 'error',
                                'field "year" is absent']);
        }
        $year = $args['year'];
        $list = [];
        $sum = 0;
        for ($m = 1; $m <= 12; $m++) {
            $row = db()->query('select sum(fuel_consumption) as summ from `boiler_statistics` ' .
                               'WHERE year(created)=%d and month(created)=%d', $year, $m);
            $sum += $row['summ'];
            $list[] = ['month' => $m,
                       'liters' => round($row['summ'] / 1000, 1)];
        }

        $stat = [];
        $stat['status'] = 'ok';
        $stat['data'] = ['year' => $year,
                         'months' => $list,
                         'total' => round($sum / 1000, 1)];
        return json_encode($stat);
    }
}


