<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';

define("CONFIG_PATH", "/etc/sr90_automation/");

function conf_global()
{
}

function conf_db()
{
    static $config = NULL;
    if (!is_array($config))
        $config = parse_json_config(CONFIG_PATH . 'database.json');

    return $config;
}

function conf_io()
{
    return array("socket_file" => '/tmp/module_io_sock');
}

function conf_guard()
{
    return array('sirena_io_port' => 3,
			     'lamp_io_port' => 4,
                 
                 // List cam in door's IO ports 
                 'doors' => array(6, // Kung
                                  5), // Container 20ft
                 'ready_set_interval' => 30, /* in seconds */
			     'light_ready_timeout' => 30 * 60, /* in seconds */
			     'light_sleep_timeout' => 30 * 60, /* in seconds */
                 'light_mode' => 'by_sensors', // 'by_sensors', 'auto', 'off'
                 'alarm_snapshot_dir' => '/var/spool/sr90_automation/images/alarm_actions',
                 'sensor_snapshot_dir' => '/var/spool/sr90_automation/images/sensor_actions',
                 'video_cameras' => array(
                                          array('id' => 1,
                                                'v4l_dev' => '/dev/video14',
                                                'resolution' => '1920:1080'),
                                          array('id' => 2,
                                                'v4l_dev' => '/dev/video15',
                                                'resolution' => '1920:1080')),
				);
}


function conf_modem()
{
    return array('ip_addr' => '192.168.1.1');
}

function conf_telegram_bot()
{
    static $config = NULL;
    if (!is_array($config))
        $config = parse_json_config(CONFIG_PATH . 'telegram.json');

    return $config;
}

