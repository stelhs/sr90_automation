<?php

require_once '/usr/local/lib/php/os.php';
require_once 'config.php';
require_once 'common_lib.php';

$player_log = new Plog('sr90:Player');

function player_start($files, $volume = 100, $duration = 0)
{
    global $player_log;
    $pid_file = PID_DIR . 'player.pid';
    stop_daemon($pid_file);

    $cmd = '';
    if (!is_array($files)) {
        $cmd = sprintf('./player.sh %s %s %s', $files, $volume, $duration);
        $player_log->info("Run playing file: %s, volume:%d, duration:%d",
                           $files, $volume, $duration);
    } else {
        foreach ($files as $file) {
            $cmd .= sprintf('./player.sh %s %s %s;', $file, $volume, $duration);
            $player_log->info("Run playing file: %s, volume:%d, duration:%d",
                               $file, $volume, $duration);
        }
    }

    run_daemon($cmd, $pid_file);
}


function player_stop($io_name, $port)
{
    $pid_file = PID_DIR . 'player.pid';
    stop_daemon($pid_file);
    run_cmd('./player.sh stop');
}
