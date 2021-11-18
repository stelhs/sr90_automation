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

                 "\t\t screenshot [cam_name] - Make camera screenshot\n" .
                 "\t\t\texample: $app_name screenshot\n" .
                 "\t\t\texample: $app_name screenshot south\n" .

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

    case 'screenshot':
        $cname = isset($argv[2]) ? $argv[2] : NULL;
        if (!$cname) {
            foreach (dvr()->cams() as $cam) {
                $fname = $cam->make_screenshot();
                if ($fname)
                    pnotice("Screenshot '%s': %s/%s\n",
                        $cam->name(),
                        conf_dvr()['storage']['snapshot_dir'],
                        $fname);
                else
                    perror("Screenshot '%s' not captured\n", $cam->name());
            }
            return 0;
        }

        $cam = dvr()->cam($cname);
        if (!$cam) {
            perror("Can't find camera %s\n", $cname);
            return -1;
        }
        $fname = $cam->make_screenshot();
        if ($fname)
            pnotice("Screenshot '%s': %s/%s\n",
                    $cam->name(),
                    conf_dvr()['storage']['snapshot_dir'],
                    $fname);
        else
            perror("Screenshot '%s' not captured\n", $cam->name());
        return 0;

    case 'openrtsp_callback':
        $log = new Plog('sr90:openRTSP_cb');
        $cname = isset($argv[2]) ? $argv[2] : NULL;
        if (!$cname) {
            perror("Camera name has not specified");
            return -1;
        }

        $cam = dvr()->cam($cname);
        if (!$cam) {
            perror("Can't find camera %s\n", $cname);
            return -1;
        }

        $start_time = isset($argv[3]) ? (int)$argv[3] : NULL;
        if (!$start_time) {
            perror("Timestamp is absent");
            return -1;
        }

        $video_file = isset($argv[4]) ? $argv[4] : NULL;
        if (!$video_file) {
            perror("video file is absent");
            return -1;
        }

        $audio_file = isset($argv[5]) ? $argv[5] : NULL;

        $cmd = sprintf('ffmpeg -i "%s" ', $video_file);
        $a_copy = '';
        if ($audio_file) {
            if ($cam->settings()['audio']['codec'] == 'PCMA')
                $cmd .= sprintf('-f alaw -ar %d -i "%s" ',
                                $cam->settings()['audio']['sample_rate'],
                                $audio_file);
            $a_copy = '-c:a aac -b:a 256k';
        }

        $dir = sprintf("%s/%s", date('Y-m/d', $start_time), $cname);
        $full_dir = sprintf("%s/%s", conf_dvr()['storage']['dir'], $dir);
        if (!file_exists($full_dir))
            mkdir($full_dir, 0777, true);

        $file_name = sprintf("%s/%s.mp4", $dir, date('H_i_s', $start_time));
        $full_file_name = sprintf('%s/%s', conf_dvr()['storage']['dir'], $file_name);

        $cmd .= sprintf('-c:v copy %s -strict -2 -f mp4 "%s"',
                        $a_copy, $full_file_name);

        unlink_safe($full_file_name);
        $ret = run_cmd($cmd);
/*        if ($cname == 'from_lamp_post') {
            file_put_contents('/root/sr90_automation/dvr_report',
                                print_r($cmd, 1) . "\n\n\n" . print_r($ret, 1));
        }*/
        if ($ret['rc']) {
            tn()->send_to_admin("can't encode video file %s",
                                $full_file_name);
            $log->err("can't encode video file %s: \n%s\n",
                                $full_file_name, $ret['log']);
            $cam->stop();
            sleep(5);
            $cam->start();
            return -1;
        }
        preg_match_all('/time=(\d{2}):(\d{2}):(\d{2})/', $ret['log'], $m);
        if (count($m) < 4) {
            tn()->send_to_admin("can't parse encoder output for file %s, ",
                                $full_file_name);
            $log->err("can't parse encoder output for file %s: \n%s\n",
                                $full_file_name, $ret['log']);
        }
        $duration = end($m[1]) * 3600 + end($m[2]) * 60 + end($m[3]);

        db()->insert('videos',
                     ['cam_name' => $cname,
                      'fname' => $file_name,
                      'duration' => $duration,
                      'file_size' => filesize($full_file_name),
                     ],
                     ['created' => sprintf('FROM_UNIXTIME(%s)', $start_time)]);

        return 0;


    default:
        perror("Invalid arguments\n");
        return -EINVAL;
    }

    return 0;
}


exit(main($argv));

