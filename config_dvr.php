<?php

function conf_dvr()
{
    return ['video_file_duration' => 60,
            'min_file_size' => 10*1024*1024,

            'storage' => ['dir' => '/storage/video-archive/',
                          'max_size_gb' => 800,
                          'snapshot_dir' => '/storage/video-archive/screenshots',
                          'http_snapshot_dir' => 'http://sr38.org:3080/dvr/videos/screenshots'],

            'site' => 'http://sr38.org:3080/dvr',

            'cameras' => [
                            ['name' => "from_lamp_post",
                             'desc' => "со столба",
                             'rtsp' => 'rtsp://192.168.10.52/stream',
                             'video' => ['codec' => 'h264',
                                         'frame_rate' => 25],
                             'audio' => ['codec' => 'PCMA',
                                         'sample_rate' => 8000],
                            ],

                            ['name' => "south",
                             'desc' => "Юг",
                             'rtsp' => 'rtsp://192.168.10.51/stream',
                             'video' => ['codec' => 'h264',
                                         'frame_rate' => 25],
                             'audio' => ['codec' => 'PCMA',
                                         'sample_rate' => 8000],
                             ],

                            ['name' => "workshop_entrance",
                             'desc' => "Площадка у мастерской",
                             'rtsp' => 'rtsp://192.168.10.53/stream',
                             'video' => ['codec' => 'h264',
                                         'frame_rate' => 25],
                             'audio' => ['codec' => 'PCMA',
                                         'sample_rate' => 8000],
                             ],

                            ['name' => "toilet",
                             'desc' => "Толчок",
                             'rtsp' => 'rtsp://192.168.10.54/stream',
                             'video' => ['codec' => 'h264',
                                         'frame_rate' => 25],
                             'audio' => ['codec' => 'PCMA',
                                         'sample_rate' => 8000],
                             ],
                            ],
            ];
}

