<?php

require_once '/usr/local/lib/php/os.php';

define("PID_DIR", "/home/stelhs/");

function sequncer_start($port, $sequence)
{
    $pid_file = sprintf(PID_DIR . 'seq_%d.pid', $port);
    stop_daemon($pid_file);
    
    $cmd = './sequencer.php ' . $port;
    foreach ($sequence as $time)
        $cmd .= ' ' . $time;
    
    run_daemon($cmd, $pid_file);
}

function sequncer_stop($port)
{
    $pid_file = sprintf(PID_DIR . 'seq_%d.pid', $port);
    $cmd = './sequencer.php ' . $port . ' 0';
    
    stop_daemon($pid_file);
    run_cmd($cmd);
}