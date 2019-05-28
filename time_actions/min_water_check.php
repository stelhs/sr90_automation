#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'guard_lib.php';
require_once 'common_lib.php';

function main($argv) {
    $ret = run_cmd("./well_pump.php duration");
    $duration = trim($ret['log']);
    if ($duration < (10 * 60))
        return 0;

    run_cmd("./well_pump.php disable");
    telegram_send_msg_admin(sprintf("Подача воды отключена по таймауту. " .
                                    "Насос проработал: %d секунд",
                                    $duration));
    return 0;
}

exit(main($argv));
