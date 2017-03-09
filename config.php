<?php
require_once '/usr/local/lib/php/os.php';

define("CONFIG_PATH", "/etc/sr90_automation/");

function conf_global()
{
    return array('phones' => array('+375295051024'));
}

function conf_db()
{
    $cfg_json = file_get_contents(CONFIG_PATH . 'database.json');
    if (!$cfg_json) {
        msg_log(LOG_ERR, sprintf("Can't open config file %s\n",
                                 CONFIG_PATH . 'database.json'));
        return null;
    }
    
    $ret = json_decode($cfg_json);
    if (!$ret) {
        msg_log(LOG_ERR, sprintf("Can't parse config file %s\n",
                                 CONFIG_PATH . 'database.json'));
        return null;
    }
    
    return (array)$ret;
}

function conf_io()
{
    return array("socket_file" => '/tmp/module_io_sock');
}

function conf_guard()
{
    return array('sirena_io_port' => 3,
			     'lamp_io_port' => 4,
                 'ready_set_interval' => 30, /* in seconds */
			     'light_ready_timeout' => 60 * 5, /* in seconds */
			     'light_sleep_timeout' => 30 * 60, /* in seconds */
                 'camera_dir' => '/var/spool/guard_system/images',
                 'video_cameras' => array(
                                          array('id' => 1,
                                                'v4l_dev' => '/dev/video14',
                                                'resolution' => '1280:1024'),
                                          array('id' => 2,
                                                'v4l_dev' => '/dev/video15',
                                                'resolution' => '1280:1024')),
				);
}


function conf_modem()
{
    return array('ip_addr' => '192.168.1.1');
}

