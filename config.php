<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';

require_once 'config_dvr.php';

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
                        1 => ['name' => 'server_power'],
                        2 => ['name' => ''],
                        3 => ['name' => ''],
                        4 => ['name' => 'vru_door', 'edge' => 'fall', 'delay' => 500],
                        5 => ['name' => 'RP_door', 'edge' => 'fall', 'delay' => 500],
                        6 => ['name' => 'guard_motion_sensor_1', 'edge' => 'fall', 'delay' => 3000],
                        7 => ['name' => 'guard_motion_sensor_2', 'edge' => 'fall', 'delay' => 3000],
                        8 => ['name' => ''],
                        9 => ['name' => ''],
                        10 => ['name' => ''],
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
                        1 => ['name' => 'ext_power'],
                        2 => ['name' => 'remote_guard_sleep', 'edge' => 'rise', 'delay' => 2000],
                        3 => ['name' => 'remote_guard_ready', 'edge' => 'rise', 'delay' => 2000],
                        4 => ['name' => 'ups_250vdc'],
                        5 => ['name' => 'ups_14vdc'],
                        6 => ['name' => 'ups_220vac'],
                        7 => ['name' => 'RP_water_pump_button', 'edge' => 'rise', 'delay' => 2000],
                        8 => ['name' => 'remote_gates_open_close', 'edge' => 'fall', 'delay' => 2000],
                        9 => ['name' => ''],
                        10 => ['name' => 'gates_motor_door', 'edge' => 'fall', 'delay' => 3000],
                        11 => ['name' => 'gates_closed'],
                        12 => ['name' => ''],
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
                        1 => ['name' => ''],
                        2 => ['name' => ''],
                        3 => ['name' => 'guard_motion_sensor_3', 'edge' => 'fall', 'delay' => 3000],
                        4 => ['name' => 'guard_motion_sensor_4', 'edge' => 'fall', 'delay' => 3000],
                        5 => ['name' => 'guard_motion_sensor_5', 'edge' => 'fall', 'delay' => 3000],
                        6 => ['name' => 'guard_motion_sensor_6', 'edge' => 'fall', 'delay' => 3000],
                        7 => ['name' => ''],
                        8 => ['name' => ''],
                        9 => ['name' => ''],
                        10 => ['name' => ''],
                        11 => ['name' => ''],
                        12 => ['name' => '']
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

        'mbio3' => ['type' => 'mbio',
                    'location' => 'Workshop',
                    'ip_addr' => '192.168.10.6',
                    'tcp_port' => 8890,
                    'in' => [
                        23 => ['name' => 'guard_motion_sensor_7', 'edge' => 'fall', 'delay' => 3000],
                        22 => ['name' => 'guard_motion_sensor_8', 'edge' => 'fall', 'delay' => 3000],
                        21 => ['name' => 'guard_motion_sensor_9', 'edge' => 'fall', 'delay' => 3000],
                        20 => ['name' => 'workshop_water_pump_button', 'edge' => 'rise', 'delay' => 1000],
                        19 => ['name' => 'guard_motion_sensor_10', 'edge' => 'fall', 'delay' => 3000],
                        18 => ['name' => 'guard_motion_sensor_11', 'edge' => 'fall', 'delay' => 3000],
                        17 => ['name' => 'guard_motion_sensor_12', 'edge' => 'fall', 'delay' => 3000],
                        16 => ['name' => 'guard_motion_sensor_13', 'edge' => 'fall', 'delay' => 3000],
                        15 => ['name' => 'guard_motion_sensor_14', 'edge' => 'fall', 'delay' => 3000],
                        14 => ['name' => 'guard_motion_sensor_15', 'edge' => 'fall', 'delay' => 3000],
                        13 => ['name' => 'guard_motion_sensor_16', 'edge' => 'fall', 'delay' => 3000],
                        12 => ['name' => 'guard_motion_sensor_17', 'edge' => 'fall', 'delay' => 3000],
                        5 =>  ['name' => 'guard_motion_sensor_18', 'edge' => 'fall', 'delay' => 3000],
                    ],
                    'out' => [
                        1 => 'workshop_power',
                        2 => 'workshop_padlock',
                        3 => 'workshop_lighter',
                        4 => 'boiler_break_power',

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

            ['name' => 'workshop_south',
             'desc' => 'Помещение-юг',
             'diff_interval' => 10,
             'alarm_time' => 300,
             'io_sensors' => ['guard_motion_sensor_18' => 0]
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
    ];
}

function conf_termo_sensors()
{
    return ['28-00000a872141' => 'на синем контейнере',
            '28-00000a87afc5' => 'внутри синего контейнера',
            '28-00000a882264' => 'под синим контейнером',
            '28-00000a5dcf5f' => 'на крыше РП',
            '28-00000a5ecf0b' => 'в помещении РП',
            '28-3c01d075cc7f' => 'в кабельном коллекторе',

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

            'light_calendar' => [1 =>  ['17:00', '9:00'],
                                 2 =>  ['17:30', '8:30'],
                                 3 =>  ['18:30', '7:00'],
                                 4 =>  ['19:30', '6:00'],
                                 5 =>  ['20:30', '5:00'],
                                 6 =>  ['22:30', '5:00'],
                                 7 =>  ['22:30', '5:00'],
                                 8 =>  ['21:00', '5:30'],
                                 9 =>  ['20:00', '6:30'],
                                 10 => ['18:30', '7:30'],
                                 11 => ['17:30', '8:30'],
                                 12 => ['17:00', '9:00'],
                                ]
           ];
}

function conf_boiler()
{
    return ['ip' => "192.168.10.10",
            'port' => "8890"];
}



