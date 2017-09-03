<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';

define("CONFIG_PATH", "/etc/sr90_automation/");
define("DISABLE_HW", 0);

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
    return array('zones' => [
                                ['id' => '1',
                                 'name' => 'зона 1',
                                 'diff_interval' => 10,
                                 'alarm_time' => 30,
                                 'run_lighter' => 1,
                                 'sensors' => [['port' => 2,
                                                'normal_state' => 1],
                                               ['port' => 4,
                                                'normal_state' => 1]]
                                ],
                                ['id' => '2',
                                 'name' => 'Датчик дверцы ВРУ',
                                 'diff_interval' => 10,
                                 'alarm_time' => 300,
                                 'run_lighter' => 1,
                                 'sensors' => [['port' => 10,
                                                'normal_state' => 1]]
                                ],
                                ['id' => '3',
                                 'name' => 'Датчик двери Кунга',
                                 'diff_interval' => 10,
                                 'alarm_time' => 300,
                                 'run_lighter' => 1,
                                 'sensors' => [['port' => 9,
                                                'normal_state' => 1]]
                                ]
                               ],
                 'sirena_io_port' => 3,
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

function conf_padlocks()
{
    return [
                ['num' => 1, 'name' => 'кунг', 'io_port' => 6],
                ['num' => 2, 'name' => 'коричневый контейнер', 'io_port' => 5],
           ];
}

function conf_street_light()
{
    return [
                ['zone' => 1, 'name' => 'уличное', 'io_port' => 4],
           ];
}
