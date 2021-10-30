#!/usr/bin/php
<?php
chdir(dirname($argv[0]));

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';

require_once 'config.php';
require_once 'common_lib.php';
require_once 'io_api.php';
require_once 'telegram_lib.php';
require_once 'dvr_api.php';

function main($argv) {
    chdir(dirname($argv[0]));
    iop('ups_break_power')->down();
    iop('battery_relay')->down();

    io()->refresh_out_ports();
    dvr()->start_recording_all();
}

exit(main($argv));