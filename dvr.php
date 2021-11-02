#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'config.php';
require_once 'common_lib.php';
require_once 'dvr_api.php';

$utility_name = $argv[0];

function print_help()
{
    global $utility_name;
    pnotice("Usage: $utility_name <command> <args>\n" .
             "\tcommands:\n" .
                 "\t\t start [cam_name] - Start cameras/camera recording\n" .
                 "\t\t\texample: $app_name start\n" .
                 "\t\t\texample: $app_name start south\n" .

                 "\t\t stop [cam_name] - Stop cameras/camera recording\n" .
                 "\t\t\texample: $app_name stop\n" .
                 "\t\t\texample: $app_name stop workshop_entrance\n" .

                 "\t\t stat - print status\n" .
                 "\t\t\texample: $app_name voice_power up \n" .

    "\n\n");
}

function main($argv)
{
    if (!isset($argv[1])) {
        print_help();
        return -EINVAL;
    }

    $cmd = strtolower($argv[1]);
    switch ($cmd) {
    case "start":
        $cname = isset($argv[2]) ? $argv[2] : NULL;
        if (!$cname) {
            dvr()->start();
            return 0;
        }

        $cam = dvr()->cam($cname);
        if (!$cam) {
            perror("Can't find camera %s\n", $cname);
            return -1;
        }
        $cam->start();
        return 0;

    case "stop":
        $cname = isset($argv[2]) ? $argv[2] : NULL;
        if (!$cname) {
            dvr()->stop();
            return 0;
        }

        $cam = dvr()->cam($cname);
        if (!$cam) {
            perror("Can't find camera %s\n", $cname);
            return -1;
        }
        $cam->stop();
        return 0;

    case "stat":
        pnotice("Cameras list:\n");
        $total_size = 0;
        foreach (dvr()->cams() as $cam) {
            $size = $cam->size();
            $size_gb = $size / (1024*1024*1024);
            pnotice("\t%s:%s, size: %.1fGb, duration: %.1f hours, %s\n",
                    $cam->name(), $cam->is_recording() ? 'running' : 'stopped',
                    $size_gb, $cam->duration() / 3600, $cam->rtsp());
            $total_size += $size;
        }

        pnotice("Maximum duration: %.1f hours\n", dvr()->duration() / 3600);
        pnotice("Total storage size: %.1fGb\n", $total_size / (1024*1024*1024));
        pnotice("Maximum storage size: %.1fGb\n\n", conf_dvr()['storage']['max_size_gb']);
        return 0;

    default:
        perror("Invalid arguments\n");
        return -EINVAL;
    }

    return 0;
}


exit(main($argv));

