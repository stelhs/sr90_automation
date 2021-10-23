<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';

define("CONFIG_PATH", "/etc/sr90_automation/");

if (is_file('DISABLE_HW'))
    define("DISABLE_HW", 1);
else
    define("DISABLE_HW", 0);


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


function conf_io()
{
    return [
        'usio1' => ['type' => 'usio',
                    'location' => 'RP',
                    'ip_addr' => 'localhost',
                    'tcp_port' => 400,
                    'in' => [
                        1 => 'server_power',
                        2 => '',
                        3 => '',
                        4 => 'vru_door',
                        5 => 'RP_door',
                        6 => 'guard_motion_sensor_1',
                        7 => 'guard_motion_sensor_2',
                        8 => '',
                        9 => '',
                        10 => ''
                    ],
                    'out' => [
                        1 => 'server_break_power',
                        2 => 'server_start',
                        3 => 'lighting_pole',
                        4 => 'gates_open_pedestration',
                        5 => 'RP_padlock',
                        6 => 'RP_sockets',
                        7 => 'guard_lamp'
                    ]],

        'sbio1' => ['type' => 'sbio',
                    'location' => 'RP',
                    'ip_addr' => '192.168.10.3',
                    'tcp_port' => 400,
                    'in' => [
                        1 => 'ext_power',
                        2 => 'remote_guard_sleep',
                        3 => 'remote_guard_ready',
                        4 => 'ups_250vdc',
                        5 => 'ups_14vdc',
                        6 => 'ups_220vac',
                        7 => 'RP_water_pump_button',
                        8 => 'remote_gates_open_close',
                        9 => '',
                        10 => 'gates_motor_door',
                        11 => 'gates_closed',
                        12 => ''
                    ],
                    'out' => [
                        1 => 'voice_power',
                        2 => 'gates_power',
                        3 => 'gates_open',
                        4 => 'charger_1.5A',
                        5 => 'charger_3A',
                        6 => 'charger_en',
                        7 => 'charge_discharge',
                        8 => 'ups_break_power',
                        9 => 'battery_relay',
                        10 => 'water_pump',
                        11 => 'gates_close'
                    ]],

        'sbio2' => ['type' => 'sbio',
                    'location' => 'SK',
                    'ip_addr' => '192.168.10.4',
                    'tcp_port' => 400,
                    'in' => [
                        1 => '',
                        2 => '',
                        3 => 'guard_motion_sensor_3',
                        4 => 'guard_motion_sensor_4',
                        5 => 'guard_motion_sensor_5',
                        6 => 'guard_motion_sensor_6',
                        7 => '',
                        8 => '',
                        9 => '',
                        10 => '',
                        11 => '',
                        12 => ''
                    ],
                    'out' => [
                        1 => 'sk_power',
                        2 => 'sk_padlock',
                        3 => 'kk_padlock',
                        4 => '',
                        5 => '',
                        6 => '',
                        7 => '',
                        8 => '',
                        9 => '',
                        10 => '',
                        11 => ''
                    ]],

        'sbio3' => ['type' => 'sbio',
                    'location' => 'Workshop',
                    'ip_addr' => '192.168.10.6',
                    'tcp_port' => 400,
                    'in' => [
                        1 => 'guard_motion_sensor_7',
                        2 => 'guard_motion_sensor_8',
                        3 => 'guard_motion_sensor_9',
                        4 => 'workshop_water_pump_button',
                        5 => 'guard_motion_sensor_10',
                        6 => 'guard_motion_sensor_11',
                        7 => 'guard_motion_sensor_12',
                        8 => 'guard_motion_sensor_13',
                        9 => 'guard_motion_sensor_14',
                        10 => 'guard_motion_sensor_15',
                        11 => 'guard_motion_sensor_16',
                        12 => 'guard_motion_sensor_17'
                    ],
                    'out' => [
                        1 => 'workshop_power',
                        2 => 'workshop_padlock',
                        3 => 'workshop_lighter',
                        4 => 'boiler_break_power',
                        5 => '',
                        6 => '',
                        7 => '',
                        8 => '',
                        9 => '',
                        10 => '',
                        11 => ''
                    ]],

        'boiler' => ['type' => 'sbio',
                     'ip_addr' => '192.168.10.10',
                     'tcp_port' => 8890,
                     'in' => [],
                     'out' => []],
    ];
}


function conf_guard()
{
    return [
        'zones' => [
            ['name' => 'north_colitis',
             'desc' => 'север калитка',
             'diff_interval' => 10,
             'alarm_time' => 60,
             'io_sensors' => ['guard_motion_sensor_12' => 0,
                              'guard_motion_sensor_13' => 0]
            ],

            ['name' => 'rp',
             'desc' => 'РП',
             'diff_interval' => 10,
             'alarm_time' => 60,
             'io_sensors' => ['guard_motion_sensor_1' => 0,
                              'guard_motion_sensor_2' => 0]
            ],

            ['name' => 'vru_door',
             'desc' => 'Дверца ВРУ',
             'diff_interval' => 4,
             'alarm_time' => 300,
             'io_sensors' => ['vru_door' => 0]
            ],

            ['name' => 'rp_door',
             'desc' => 'Дверь РП',
             'diff_interval' => 4,
             'alarm_time' => 300,
             'io_sensors' => ['RP_door' => 0]
            ],

            ['name' => 'south_street',
             'desc' => 'Юг улица',
             'diff_interval' => 10,
             'alarm_time' => 60,
             'io_sensors' => ['guard_motion_sensor_3' => 0,
                              'guard_motion_sensor_4' => 0]
            ],

            ['name' => 'workshop_west',
             'desc' => 'Помещение-запад',
             'diff_interval' => 10,
             'alarm_time' => 300,
             'io_sensors' => ['guard_motion_sensor_7' => 0]
            ],

            ['name' => 'workshop_north',
             'desc' => 'Помещение-север',
             'diff_interval' => 10,
             'alarm_time' => 300,
             'io_sensors' => ['guard_motion_sensor_8' => 0]
            ],

            ['name' => 'workshop_east',
             'desc' => 'Помещение-восток',
             'diff_interval' => 10,
             'alarm_time' => 300,
             'io_sensors' => ['guard_motion_sensor_9' => 0]
            ],

            ['name' => 'road_east',
             'desc' => 'Дорога-восток',
             'diff_interval' => 10,
             'alarm_time' => 60,
             'io_sensors' => ['guard_motion_sensor_5' => 0,
                              'guard_motion_sensor_6' => 0]
            ],

            ['name' => 'west_street',
             'desc' => 'Запад улица',
             'diff_interval' => 10,
             'alarm_time' => 60,
             'io_sensors' => ['guard_motion_sensor_10' => 0,
                              'guard_motion_sensor_11' => 0]
            ],

            ['name' => 'workshop_street',
             'desc' => 'Площадка у мастерской',
             'diff_interval' => 10,
             'alarm_time' => 60,
             'io_sensors' => ['guard_motion_sensor_16' => 0,
                              'guard_motion_sensor_17' => 0]
            ],

            ['name' => 'under_balcony',
             'desc' => 'Под балконом',
             'diff_interval' => 10,
             'alarm_time' => 60,
             'io_sensors' => ['guard_motion_sensor_14' => 0,
                              'guard_motion_sensor_15' => 0]
            ],

            ['name' => 'gates_motor_door',
             'desc' => 'Дверца откатных ворот',
             'diff_interval' => 4,
             'alarm_time' => 300,
             'io_sensors' => ['gates_motor_door' => 0]
            ],

            ['name' => 'gates_closed',
             'desc' => 'Откатные ворота',
             'alarm_time' => 300,
             'skip_ignore' => true,
             'io_sensors' => ['gates_closed' => 0]
            ],
        ],

        'ready_set_interval' => 30, /* in seconds */
        'light_ready_timeout' => 30 * 60, /* in seconds */
        'light_sleep_timeout' => 30 * 60, /* in seconds */
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
        ['name' => 'rp', 'desc' => 'РП', 'port' => 'RP_padlock'],
        ['name' => 'kk', 'desc' => 'Коричневый контейнер', 'port' => 'kk_padlock'],
        ['name' => 'sk', 'desc' => 'Синий контейнер', 'port' => 'sk_padlock'],
        ['name' => 'workshop', 'desc' => 'Мастерская', 'port' => 'workshop_padlock'],
    ];
}

function conf_street_light()
{
    return ['lights' => [
                         ['name' => 'lighting_pole',
                          'desc' => 'Столб',
                          'port' => 'lighting_pole'],

                         ['name' => 'workshop',
                          'desc' => 'Мастерская',
                          'port' => 'workshop_lighter'],
                         ],

            'light_calendar' => [1 =>  ['16:00', '9:30'],
                                 2 =>  ['17:00', '8:30'],
                                 3 =>  ['18:30', '7:00'],
                                 4 =>  ['19:30', '6:00'],
                                 5 =>  ['20:30', '5:00'],
                                 6 =>  ['22:30', '5:00'],
                                 7 =>  ['22:30', '5:00'],
                                 8 =>  ['21:00', '5:30'],
                                 9 =>  ['20:00', '6:30'],
                                 10 => ['18:30', '7:30'],
                                 11 => ['17:00', '8:30'],
                                 12 => ['16:30', '9:00'],
                                ]
           ];
}

function conf_boiler()
{
    return ['ip' => "192.168.10.10",
            'port' => "8890"];
}



