<?php

require_once '/usr/local/lib/php/os.php';
require_once 'config.php';

define("PID_DIR", getenv('HOME') . '/');

function player_start($files, $volume = 100)
{
    $pid_file = PID_DIR . 'player.pid';
    stop_daemon($pid_file);

    $cmd = '';
    if (!is_array($files))
        $cmd = sprintf('./player.sh %s %s', $files, $volume);
    else
        foreach ($files as $file)
            $cmd .= sprintf('./player.sh %s %s;', $file, $volume);

    dump($cmd);
    run_daemon($cmd, $pid_file);
}

function player_stop($io_name, $port)
{
    $pid_file = PID_DIR . 'player.pid';
    stop_daemon($pid_file);
    run_cmd('./player.sh stop');
}
