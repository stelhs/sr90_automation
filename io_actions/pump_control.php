#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'common_lib.php';
require_once 'guard_lib.php';
require_once 'telegram_api.php';


function main($argv)
{
    if (count($argv) < 4) {
        printf("a few scripts parameters\n");
        return -EINVAL;
    }

    $io_name = $argv[1];
    $port = $argv[2];
    $port_state = $argv[3];
    printf("io_name = %s\n", $io_name);
    printf("port = %s\n", $port);
    printf("port_state = %s\n", $port_state);

    if ($io_name != 'sbio1' || $port != 7 || $port_state != 1)
        return;

    $guard_state = get_guard_state();
    if ($guard_state['state'] == 'ready') {
        run_cmd("./image_sender.php current", TRUE);
        telegram_send_msg("Ктото нажал на кнопку подачи воды");
        return;
    }

    $ret = run_cmd("./well_pump.php duration");
    $duration = trim($ret['log']);
    if (!$duration) {
        run_cmd(sprintf("./well_pump.php enable"));
        telegram_send_msg_admin("Подача воды включена");
        return;
    }

    if ($duration < 5)
        return;

    run_cmd("./well_pump.php disable");
    telegram_send_msg_admin("Подача воды отключена");
}

exit(main($argv));
