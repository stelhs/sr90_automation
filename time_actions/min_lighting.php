#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'guard_lib.php';
require_once 'common_lib.php';

define("DAY_NIGHT_MODE_FILE", "/tmp/day_night_mode");

function main($argv) {
    @$prev_mode = file_get_contents(DAY_NIGHT_MODE_FILE);
    $curr_mode = get_day_night();
    if ($curr_mode == $prev_mode)
        return 0;

    file_put_contents(DAY_NIGHT_MODE_FILE, $curr_mode);

    if ($curr_mode == 'day') {
        $ret = run_cmd('./street_light.php disable');
        if ($ret['rc']) {
            perror("Can't disable street_light: %s\n", $ret['log']);
            return $rc;
        }
        return 0;
    }

    if (conf_guard()['light_mode'] == 'off')
        return 0;

    $ret = run_cmd('./street_light.php enable 0 1');
    if ($ret['rc']) {
        perror("Can't enable street_light: %s\n", $ret['log']);
        return $rc;
    }

    $guard_info = get_guard_state();
    if ($guard_info['state'] == 'ready' &&
            conf_guard()['light_mode'] == 'by_sensors')
        return 0;

    $ret = run_cmd('./street_light.php enable 0 2');
    if ($ret['rc']) {
        perror("Can't enable street_light: %s\n", $ret['log']);
        return $rc;
    }
    return 0;
}

exit(main($argv));