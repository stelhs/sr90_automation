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
                                 'name' => 'север колитка',
                                 'diff_interval' => 15,
                                 'alarm_time' => 60,
                                 'run_lighter' => 1,
                                 'sensors' => [['io' => 'sbio3',
                                                'port' => 7,
                                                'normal_state' => 1],
                                               ['io' => 'sbio3',
                                                'port' => 8,
                                                'normal_state' => 1]]
                                ],
                                ['id' => '2',
                                    'name' => 'РП',
                                    'diff_interval' => 15,
                                    'alarm_time' => 60,
                                    'run_lighter' => 1,
                                    'sensors' => [['io' => 'usio1',
                                                   'port' => 6,
                                                   'normal_state' => 1],
                                                  ['io' => 'usio1',
                                                   'port' => 7,
                                                  'normal_state' => 1]]
                                ],
                                ['id' => '3',
                                 'name' => 'Дверца ВРУ',
                                 'diff_interval' => 4,
                                 'alarm_time' => 300,
                                 'run_lighter' => 1,
                                 'sensors' => [['io' => 'usio1',
                                                'port' => 4,
                                                'normal_state' => 1]]
                                ],
                                ['id' => '4',
                                 'name' => 'Дверь РП',
                                 'diff_interval' => 4,
                                 'alarm_time' => 300,
                                 'run_lighter' => 1,
                                 'sensors' => [['io' => 'usio1',
                                                'port' => 5,
                                                'normal_state' => 1]]
                                ],
                                ['id' => '6',
                                    'name' => 'Юг улица',
                                    'diff_interval' => 15,
                                    'alarm_time' => 60,
                                    'run_lighter' => 1,
                                    'sensors' => [['io' => 'sbio2',
                                                   'port' => 3,
                                                   'normal_state' => 1],
                                                  ['io' => 'sbio2',
                                                   'port' => 4,
                                                  'normal_state' => 1]]
                                ],
                                ['id' => '7',
                                    'name' => 'Помещение-запад',
                                    'diff_interval' => 15,
                                    'alarm_time' => 300,
                                    'run_lighter' => 1,
                                    'sensors' => [['io' => 'sbio3',
                                        'port' => 1,
                                        'normal_state' => 1]]
                                ],
                                ['id' => '8',
                                    'name' => 'Помещение-север',
                                    'diff_interval' => 15,
                                    'alarm_time' => 300,
                                    'run_lighter' => 1,
                                    'sensors' => [['io' => 'sbio3',
                                        'port' => 2,
                                        'normal_state' => 1]]
                                ],
                                ['id' => '9',
                                    'name' => 'Помещение-восток',
                                    'diff_interval' => 15,
                                    'alarm_time' => 300,
                                    'run_lighter' => 1,
                                    'sensors' => [['io' => 'sbio3',
                                        'port' => 3,
                                        'normal_state' => 1]]
                                ],
                             /*   ['id' => '10',
                                    'name' => 'Помещение-юг',
                                    'diff_interval' => 15,
                                    'alarm_time' => 300,
                                    'run_lighter' => 1,
                                    'sensors' => [['io' => 'sbio3',
                                        'port' => 4,
                                        'normal_state' => 1]]
				],*/
                                ['id' => '11',
                                    'name' => 'Дорога-восток',
                                    'diff_interval' => 15,
                                    'alarm_time' => 60,
                                    'run_lighter' => 1,
                                    'sensors' => [['io' => 'sbio2',
                                                   'port' => 5,
                                                   'normal_state' => 1],
                                                  ['io' => 'sbio2',
                                                   'port' => 6,
                                                  'normal_state' => 1]]
				],
                                ['id' => '12',
                                    'name' => 'Запад улица',
                                    'diff_interval' => 15,
                                    'alarm_time' => 60,
                                    'run_lighter' => 1,
                                    'sensors' => [['io' => 'sbio3',
                                                   'port' => 5,
                                                   'normal_state' => 1],
                                                  ['io' => 'sbio3',
                                                   'port' => 6,
                                                  'normal_state' => 1]]
                                ],
                                ['id' => '13',
                                    'name' => 'Площадка ворота',
                                    'diff_interval' => 15,
                                    'alarm_time' => 60,
                                    'run_lighter' => 1,
                                    'sensors' => [['io' => 'sbio3',
                                                   'port' => 11,
                                                   'normal_state' => 1],
                                                  ['io' => 'sbio3',
                                                   'port' => 12,
                                                  'normal_state' => 1]]
                                ],
                                ['id' => '14',
                                    'name' => 'Под балконом',
                                    'diff_interval' => 15,
                                    'alarm_time' => 60,
                                    'run_lighter' => 1,
                                    'sensors' => [['io' => 'sbio3',
                                                   'port' => 9,
                                                   'normal_state' => 1],
                                                  ['io' => 'sbio3',
                                                   'port' => 10,
                                                  'normal_state' => 1]]
                                ],
                                ['id' => '15',
                                 'name' => 'Дверца откатных ворот',
                                 'diff_interval' => 4,
                                 'alarm_time' => 300,
                                 'run_lighter' => 1,
                                 'sensors' => [['io' => 'sbio1',
                                                'port' => 10,
                                                'normal_state' => 1]]
                                ],
                               ],
                 'ready_set_interval' => 30, /* in seconds */
			     'light_ready_timeout' => 30 * 60, /* in seconds */
			     'light_sleep_timeout' => 30 * 60, /* in seconds */
                 'light_mode' => 'by_sensors', // 'by_sensors', 'auto', 'off'
                 'alarm_snapshot_dir' => '/storage/sr90_automation/images/alarm_actions',
                 'sensor_snapshot_dir' => '/storage/sr90_automation/images/sensor_actions',
                 'video_cameras' => [
                                      ['id' => 1,
                                       'name' => "01-Kamera_1",
                                       'v4l_dev' => '/dev/video14',
                                       'resolution' => '1920:1080'],

                                      ['id' => 2,
                                       'name' => "02-Kamera_2",
                                       'v4l_dev' => '/dev/video15',
                                       'resolution' => '1920:1080'],

                                      ['id' => 3,
                                       'name' => "03-Kamera_3",
                                       'v4l_dev' => '/dev/video16',
                                       'resolution' => '1920:1080'],
                                     ['id' => 4,
                                         'name' => "04-Kamera_4",
                                         'v4l_dev' => '/dev/video17',
                                         'resolution' => '1280:1024']

                 ],
                 'remote_control_sleep' => ['io' => 'sbio1', 'port' => 2],
                 'remote_control_ready' => ['io' => 'sbio1', 'port' => 3],
    );
}

function conf_io()
{
        return ['usio1' => ['ip_addr' => 'localhost',
                            'tcp_port' => 400,
                            'in_ports' => 10,
                            'out_ports' => 7],
                'sbio1' => ['ip_addr' => '192.168.10.3',
                            'tcp_port' => 400,
                            'in_ports' => 12,
                            'out_ports' => 11],
                'sbio2' => ['ip_addr' => '192.168.10.4',
                            'tcp_port' => 400,
                            'in_ports' => 12,
                            'out_ports' => 11],
                'sbio3' => ['ip_addr' => '192.168.10.6',
                            'tcp_port' => 400,
                            'in_ports' => 12,
                            'out_ports' => 11],
                'boiler' => ['ip_addr' => '192.168.10.10',
                            'tcp_port' => 8890,
                            'in_ports' => 0,
			                'out_ports' => 0],
		    ];
}

function conf_termo_sensors()
{
    return ['28-00000a872141' => 'на синем контейнере',
            '28-00000a87afc5' => 'внутри синего контейнера',
            '28-00000a882264' => 'под синим контейнером',
            '28-00000a5dcf5f' => 'на крыше РП',
            '28-00000a5ecf0b' => 'в помещении РП',
            '28-00000a5ed3e0' => 'в кабельном коллекторе',

            '28-012033f3fd8f' => 'в мастерской',
            '28-012033e26477' => 'в котле',
            '28-012033f9c648' => 'в корпусе котла',
            '28-012033e45839' => 'в радиаторах отопления',
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
                ['num' => 1, 'name' => 'РП', 'io' => 'usio1', 'io_port' => 5],
                ['num' => 3, 'name' => 'коричневый контейнер', 'io' => 'sbio2', 'io_port' => 3],
                ['num' => 4, 'name' => 'синий контейнер', 'io' => 'sbio2', 'io_port' => 2],
                ['num' => 5, 'name' => 'мастерская', 'io' => 'sbio3', 'io_port' => 2],
    ];
}

function conf_street_light()
{
    return [
                ['zone' => 1, 'name' => 'слабое РП', 'io' => 'usio1', 'io_port' => 3],
        		['zone' => 3, 'name' => 'мастерская', 'io' => 'sbio3', 'io_port' => 3],
           ];
}

function conf_ups()
{
    return ['charger_enable_port' => ['io' => 'sbio1', 'out_port' => '6'],
            'middle_current_enable_port' => ['io' => 'sbio1', 'out_port' => '4'],
            'full_current_enable_port' => ['io' => 'sbio1', 'out_port' => '5'],
            'discharge_enable_port' => ['io' => 'sbio1', 'out_port' => '7'],
            'external_input_power_port' => ['io' => 'sbio1', 'in_port' => '1'],
            'external_ups_power_port' => ['io' => 'sbio1', 'in_port' => '6'],
            'stop_ups_power_port' => ['io' => 'sbio1', 'out_port' => '8'],
            'stop_ups_battery_port' => ['io' => 'sbio1', 'out_port' => '9'],
            'vdc_out_check_port' => ['io' => 'sbio1', 'in_port' => '4'],
            'standby_check_port' => ['io' => 'sbio1', 'in_port' => '5'],
    ];
}

function conf_water()
{
    return ['well_pump_enable_port' => ['io' => 'sbio1', 'out_port' => 10]];
}


function conf_boiler()
{
    return ['ip' => "192.168.10.10",
            'port' => "8890"];
}
