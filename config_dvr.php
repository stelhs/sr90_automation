<?php

function conf_dvr()
{
    return ['video_file_duration' => 60,
            'min_file_size' => 10*1024*1024,

            'storage' => ['dir' => '/storage/video-archive/',
                          'max_size_gb' => 7000,
                          'snapshot_dir' => '/storage/video-archive/screenshots',
                          'http_snapshot_dir' => 'http://sr38.org:3080/dvr/videos/screenshots'],

            'site' => 'http://sr38.org:3080/dvr',

            'cameras' => [
                            ['name' => "from_lamp_post",
                             'desc' => "со столба основная",
                             'rtsp' => 'rtsp://192.168.10.52/stream',
                             'video' => ['codec' => 'h264',
                                         'frame_rate' => 25],
                             'audio' => ['codec' => 'PCMA',
                                         'sample_rate' => 8000],
                             'recording' => true,
                             'viewing' => true,
                             'private' => false,
                             'hide_errors' => false,
                            ],

                            ['name' => "south",
                             'desc' => "Юг",
                             'rtsp' => 'rtsp://192.168.10.51/stream',
                             'video' => ['codec' => 'h264',
                                         'frame_rate' => 25],
                             'audio' => ['codec' => 'PCMA',
                                         'sample_rate' => 8000],
                             'recording' => true,
                             'viewing' => true,
                             'private' => false,
                             'hide_errors' => false,
                             ],

                            ['name' => "workshop_entrance",
                             'desc' => "Площадка у мастерской",
                             'rtsp' => 'rtsp://192.168.10.53/stream',
                             'video' => ['codec' => 'h264',
                                         'frame_rate' => 25],
                             'recording' => true,
                             'viewing' => true,
                             'private' => false,
                             'hide_errors' => true,
                		     ],

                            ['name' => "toilet",
                             'desc' => "Толчок",
                             'rtsp' => 'rtsp://192.168.10.54/stream',
                             'video' => ['codec' => 'h264',
                                         'frame_rate' => 25],
                             'recording' => true,
                             'viewing' => true,
                             'private' => false,
                             'hide_errors' => true,
				             ],

                            ['name' => "west",
                             'desc' => "Запад с мастерской",
                             'rtsp' => 'rtsp://192.168.10.55/user=admin&password=&channel=0&stream=0.sdp',
                             'video' => ['codec' => 'h264',
                                         'frame_rate' => 30],
                             'audio' => ['codec' => 'PCMA',
                                         'sample_rate' => 8000],
                             'recording' => true,
                             'viewing' => true,
                             'private' => false,
                             'hide_errors' => false,
                             ],

                            ['name' => "west_post",
                             'desc' => "Запад со столба",
                             'rtsp' => 'rtsp://192.168.10.56/stream?user=admin&password=&channel=0&stream=0.sdp',
                             'video' => ['codec' => 'h264',
                                         'frame_rate' => 30],
                             'audio' => ['codec' => 'PCMA',
                                         'sample_rate' => 8000],
                             'recording' => true,
                             'viewing' => true,
                             'private' => false,
                             'hide_errors' => false,
                             ],

                            ['name' => "east",
                             'desc' => "Восток",
                             'rtsp' => 'rtsp://192.168.10.57/user=admin&password=&channel=0&stream=0.sdp',
                             'video' => ['codec' => 'h264',
                                         'frame_rate' => 30],
                             'recording' => true,
                             'viewing' => true,
                             'private' => false,
                             'hide_errors' => false,
                             ],

                            ['name' => "workshop_1",
                             'desc' => "мастерская угол у ВРУ",
                             'rtsp' => 'rtsp://192.168.10.58/stream?user=admin&password=&channel=0&stream=0.sdp',
                             'video' => ['codec' => 'h264',
                                         'frame_rate' => 30],
                             'audio' => ['codec' => 'PCMA',
                                         'sample_rate' => 8000],
                             'recording' => true,
                             'viewing' => true,
                             'private' => true,
                             'hide_errors' => true,
                             ],

                            ['name' => "workshop_2",
                             'desc' => "мастерская угол у ворот",
                             'rtsp' => 'rtsp://192.168.10.59/user=admin&password=&channel=0&stream=0.sdp',
                             'video' => ['codec' => 'h264',
                                         'frame_rate' => 30],
                             'recording' => true,
                             'viewing' => true,
                             'private' => true,
                             'hide_errors' => false,
                             ],

                            ['name' => "workshop_3",
                             'desc' => "мастерская второй этаж",
                             'rtsp' => 'rtsp://192.168.10.60/user=admin&password=&channel=0&stream=0.sdp',
                             'video' => ['codec' => 'h264',
                                         'frame_rate' => 30],
                             'audio' => ['codec' => 'PCMA',
                                         'sample_rate' => 8000],
                             'recording' => true,
                             'viewing' => true,
                             'private' => true,
                             'hide_errors' => false,
                            ],

                            ['name' => "north-gate",
                             'desc' => "калитка на севере",
                             'rtsp' => 'rtsp://192.168.10.61/user=admin&password=&channel=0&stream=0.sdp',
                             'video' => ['codec' => 'h264',
                                         'frame_rate' => 30],
                             'recording' => true,
                             'viewing' => true,
                             'private' => false,
                             'hide_errors' => false,
                            ],

                            ],
            ];
}

