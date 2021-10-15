<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once 'httpio_lib.php';

define("GATES_CLOSE_AFTER", "/tmp/gates_close_after");

function gates_io()
{
    return ['enable' => httpio_port(conf_gates()['enable_port']),
            'open' => httpio_port(conf_gates()['open_port']),
            'open_ped' => httpio_port(conf_gates()['open_ped_port']),
            'close' => httpio_port(conf_gates()['close_port']),
            'state' => httpio_port(conf_gates()['state_port']) ];
}

function gates_power_enable()
{
    gates_io()['enable']->set(1);
    return 0;
}

function gates_power_disable()
{
    if (gates_io()['state']->get() == 0)
        return -EBUSY;
    gates_io()['enable']->set(0);
    gates_close_after_cancel();
    return 0;
}

function gates_open()
{
    if (!gates_io()['enable']->get())
        return -EBUSY;
    gates_io()['open']->set(1);
    sleep(1);
    gates_io()['open']->set(0);
    gates_close_after_cancel();
    return 0;
}

function gates_open_ped()
{
    if (!gates_io()['enable']->get())
        return -EBUSY;
    gates_io()['open_ped']->set(1);
    sleep(1);
    gates_io()['open_ped']->set(0);
    gates_close_after_cancel();
    return 0;
}

function gates_close()
{
    if (!gates_io()['enable']->get())
        return -EBUSY;
    gates_io()['close']->set(1);
    sleep(1);
    gates_io()['close']->set(0);
    gates_close_after_cancel();
    return 0;
}

function gates_close_after($time)
{
    file_put_contents(GATES_CLOSE_AFTER, time() + $time);
}

function gates_close_after_cancel()
{
    @unlink(GATES_CLOSE_AFTER);
}

function gates_close_sync()
{
    if (!gates_io()['enable']->get())
        return -EBUSY;

    gates_close_after_cancel();
    gates_io()['close']->set(1);
    sleep(1);
    gates_io()['close']->set(0);
    for($sec = 0; $sec < 100; $sec++) {
        if (gates_io()['state']->get()) {
            return 0;
        }
        sleep(1);
    }
    return -ECONNFAIL;
}

function gates_stat()
{
    $stat = [];
    if (gates_io()['enable']->get())
        $stat['power'] = "enabled";
    else
        $stat['power'] = "disabled";

    if (gates_io()['state']->get())
        $stat['gates'] = "closed";
    else
        $stat['gates'] = "not_closed";
    return $stat;
}


