<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once 'common_lib.php';


function boiler_stat()
{
    $http_request = sprintf("http://%s:%d/boiler",
                            conf_boiler()['ip'],
                            conf_boiler()['port']);
    $content = file_get_contents($http_request);
    if (!$content)
        return -1;

    $stat = json_decode($content, true);
    if (!$stat)
        return -1;

    return $stat;
}


function boiler_reset_stat()
{
    $http_request = sprintf("http://%s:%d/boiler/reset_stat",
                            conf_boiler()['ip'],
                            conf_boiler()['port']);
    $content = file_get_contents($http_request);
    if (!$content)
        return -1;

    return 0;
}


function boiler_start()
{
    $http_request = sprintf("http://%s:%d/boiler/start",
                            conf_boiler()['ip'],
                            conf_boiler()['port']);
    $content = file_get_contents($http_request);
    if (!$content)
        return -1;

    $ret = json_decode($content, true);
    if (!$ret)
        return -1;

    if ($ret['status'] != 'ok')
        return -1;

    return 0;
}


function boiler_stop()
{
    $http_request = sprintf("http://%s:%d/boiler/stop",
                            conf_boiler()['ip'],
                            conf_boiler()['port']);
    $content = file_get_contents($http_request);
    if (!$content)
        return -1;

    $ret = json_decode($content, true);
    if (!$ret)
        return -1;

    if ($ret['status'] != 'ok')
        return -1;

    return 0;
}

function boiler_set_room_t($t)
{
    $http_request = sprintf("http://%s:%d/boiler/setup?target_room_t=%.1f",
                            conf_boiler()['ip'],
                            conf_boiler()['port'],
                            $t);
    $content = file_get_contents($http_request);
    if (!$content)
        return -1;

    $ret = json_decode($content, true);
    if (!$ret)
        return -1;

    if ($ret['status'] != 'ok')
        return -1;

    return 0;
}
