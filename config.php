<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';

define("CONFIG_PATH", "/etc/sr90_automation/");

if (is_file('DISABLE_HW'))
    define("DISABLE_HW", 1);
else
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

function conf_local_io()
{
    return array("socket_file" => '/tmp/usio_sock');
}

function conf_guard()
{
    return array('zones' => [
                                ['id' => '1',
                                 'name' => 'кунг',
                                 'diff_interval' => 10,
                                 'alarm_time' => 30,
                                 'run_lighter' => 1,
                                 'sensors' => [['io' => 'usio1',
                                                'port' => 2,
                                                'normal_state' => 1],
                                               ['io' => 'usio1',
                                                'port' => 3,
                                                'normal_state' => 1]]
                                ],
                                ['id' => '2',
                                    'name' => 'РП',
                                    'diff_interval' => 10,
                                    'alarm_time' => 30,
                                    'run_lighter' => 1,
                                    'sensors' => [['io' => 'usio1',
                                                   'port' => 6,
                                                   'normal_state' => 1],
                                                  ['io' => 'usio1',
                                                   'port' => 7,
                                                  'normal_state' => 1]]
                                ],
                                ['id' => '3',
                                 'name' => 'Датчик дверцы ВРУ',
                                 'diff_interval' => 4,
                                 'alarm_time' => 300,
                                 'run_lighter' => 1,
                                 'sensors' => [['io' => 'usio1',
                                                'port' => 4,
                                                'normal_state' => 1]]
                                ],
                                ['id' => '4',
                                 'name' => 'Датчик двери РП',
                                 'diff_interval' => 4,
                                 'alarm_time' => 300,
                                 'run_lighter' => 1,
                                 'sensors' => [['io' => 'usio1',
                                                'port' => 5,
                                                'normal_state' => 1]]
                                ],
/*                                ['id' => '3',
                                 'name' => 'Датчик двери Кунга',
                                 'diff_interval' => 10,
                                 'alarm_time' => 300,
                                 'run_lighter' => 1,
                                 'sensors' => [['io' => 'usio1',
                                                'port' => 5,
                                                'normal_state' => 1]]
                                ]*/
                               ],
                 'ready_set_interval' => 30, /* in seconds */
			     'light_ready_timeout' => 30 * 60, /* in seconds */
			     'light_sleep_timeout' => 30 * 60, /* in seconds */
                 'light_mode' => 'by_sensors', // 'by_sensors', 'auto', 'off'
                 'alarm_snapshot_dir' => '/var/spool/sr90_automation/images/alarm_actions',
                 'sensor_snapshot_dir' => '/var/spool/sr90_automation/images/sensor_actions',
                 'video_cameras' => [
                                      ['id' => 1,
                                            'name' => "01-Kamera_1",
                                            'v4l_dev' => '/dev/video14',
                                            'resolution' => '1920:1080'],
                                      ['id' => 2,
                                            'name' => "02-Kamera_2",
                                            'v4l_dev' => '/dev/video15',
                                            'resolution' => '1920:1080']
                                    ],
                 'remote_control_sleep' => ['io' => 'sbio1', 'port' => 2],
                 'remote_control_ready' => ['io' => 'sbio1', 'port' => 3],
    );
}

function conf_io()
{
        return ['usio1' => ['ip_addr' => 'localhost',
                            'tcp_port' => 400],
                'sbio1' => ['ip_addr' => '192.168.10.3',
                            'tcp_port' => 400],
                'sbio2' => ['ip_addr' => '192.168.10.4',
                            'tcp_port' => 400],
               ];
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
                ['num' => 1, 'name' => 'кунг', 'io' => 'usio1', 'io_port' => 6],
                ['num' => 2, 'name' => 'коричневый контейнер', 'io' => 'usio1', 'io_port' => 6],
                ['num' => 1, 'name' => 'РП', 'io' => 'usio1', 'io_port' => 5],
                ];
}

function conf_street_light()
{
    return [
                ['zone' => 1, 'name' => 'слабое', 'io' => 'usio1', 'io_port' => 3],
                ['zone' => 2, 'name' => 'основное', 'io' => 'usio1', 'io_port' => 4],
                ];
}

