<?php

require_once '/usr/local/lib/php/os.php';
require_once 'config.php';

define("PID_DIR", getenv('HOME') . '/');

function player_start($files, $volume = 100, $duration = 0)
{
    $pid_file = PID_DIR . 'player.pid';
    stop_daemon($pid_file);

    $cmd = '';
    if (!is_array($files))
        $cmd = sprintf('./player.sh %s %s %s', $files, $volume, $duration);
    else
        foreach ($files as $file)
            $cmd .= sprintf('./player.sh %s %s %s;', $file, $volume, $duration);

    run_daemon($cmd, $pid_file);
}

function player_stop($io_name, $port)
{
    $pid_file = PID_DIR . 'player.pid';
    stop_daemon($pid_file);
    run_cmd('./player.sh stop');
}
