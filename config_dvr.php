<?php

function conf_dvr()
{
    return ['video_file_duration' => 60,

            'storage' => ['dir' => '/storage/video-archive',
                          'max_size_gb' => 700],

            'site' => 'http://sr38.org:3080/dvr',

            'cameras' => [
                            ['name' => "south",
                             'desc' => "Юг",
                             'rtsp' => 'rtsp://192.168.10.51/stream'],

                            ['name' => "from_lamp_post",
                             'desc' => "со столба",
                             'rtsp' => 'rtsp://192.168.10.52/stream'],

                            ['name' => "workshop_entrance",
                             'desc' => "Площадка у мастерской",
                             'rtsp' => 'rtsp://192.168.10.53/stream'],

                            ['name' => "toilet",
                             'desc' => "Толчок",
                             'rtsp' => 'rtsp://192.168.10.54/stream'],
                            ],
            ];
}

