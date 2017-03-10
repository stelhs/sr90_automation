#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/database.php';
require_once 'config.php';
require_once 'mod_io_lib.php';
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
        $mio->relay_set_state(conf_guard()['lamp_io_port'], 0);
        run_cmd("./modem.php sms_send_users 'Прожектор отключился потому что стало светло'");
        return 0;
    }

    if (conf_guard()['light_mode'] != 'auto')
        return 0;
    
    $mio->relay_set_state(conf_guard()['lamp_io_port'], 1);
    run_cmd("./modem.php sms_send_users 'Прожектор включился потому что стало темно'");
    return 0;
}

return main($argv);