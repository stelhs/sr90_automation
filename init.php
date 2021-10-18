#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';

require_once 'config.php';
require_once 'common_lib.php';
require_once 'board_io_api.php';
require_once 'telegram_lib.php';

function main($argv) {
    iop('ups_break_power')->down();
    iop('battery_relay')->down();
    tn()->send_to_admin('Сервер sr90 перезапущен');

    refresh_out_ports();

}

exit(main($argv));