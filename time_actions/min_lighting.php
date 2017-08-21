#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/database.php';
require_once 'config.php';
require_once 'mod_io_lib.php';
require_once 'guard_lib.php';
require_once 'server_control_lib.php';

define("DAY_NIGHT_MODE_FILE", "/tmp/day_night_mode");

function main($argv) {
    @$prev_mode = file_get_contents(DAY_NIGHT_MODE_FILE);
    $curr_mode = get_day_night();
    if ($curr_mode == $prev_mode)
        return 0;
    
    file_put_contents(DAY_NIGHT_MODE_FILE, $curr_mode);

    $db = new Database;
    $rc = $db->connect(conf_db());
    if ($rc) {
        printf("can't connect to database");
        return -EBASE;
    }    
    $mio = new Mod_io($db);

    if ($curr_mode == 'day') {
        $ret = run_cmd('./street_light.php disable');
        if ($ret['rc'])
            msg_log(LOG_ERR, "Can't disable street_light: " . $ret['log']);
        return 0;
    }

    if (conf_guard()['light_mode'] == 'off')
        return 0;

    $guard_info = get_guard_state($db);
    if ($guard_info['state'] == 'ready' && conf_guard()['light_mode'] == 'by_sensors')
        return 0;

    $ret = run_cmd('./street_light.php enable');
    if ($ret['rc'])
        msg_log(LOG_ERR, "Can't enable street_light: " . $ret['log']);

    return 0;
}

return main($argv);