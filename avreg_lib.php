<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';

define("AVREG_VIDEO_DIR", "/storage/avreg/");
class Avreg {
    function __construct()
    {
        $this->log = new Plog("sr90:Avreg");
    }

    function video_file_timestamp($full_file_name)
    {
        preg_match('/(\d{4}-\d{2})\/(\d{2})\/.*(\d{2}_\d{2}_\d{2}).*/', $full_file_name, $mathes);
        if (!isset($mathes[0]) || !trim($mathes[0]))
            return false;

        $file_time = date_create_from_format("Y-m-d H_i_s",
                                             sprintf("%s-%s %s",
                                                     $mathes[1],
                                                     $mathes[2],
                                                     $mathes[3]));
        $file_stamp = $file_time->getTimestamp();
        return $file_stamp;
    }

    function sorted_video_list($cam_name)
    {
        $video_list = [];
        $dirs1 = get_list_subdirs(AVREG_VIDEO_DIR);
        if (!$dirs1) {
            $this->log->err("Can't find directory: %s\n", AVREG_VIDEO_DIR);
            return false;
        }

        if (!count($dirs1)) {
            $this->log->err("Directory %s is empty: %os\n", AVREG_VIDEO_DIR);
            return [];
        }

        foreach ($dirs1 as $dir1) {
            $dirs2 = get_list_subdirs(AVREG_VIDEO_DIR . $dir1 . '/');
            if (!$dirs2 || !count($dirs2))
                continue;

            foreach ($dirs2 as $dir2) {
                $files = get_list_files(AVREG_VIDEO_DIR . $dir1 . '/' . $dir2 . '/' . $cam_name . '/');
                if (!$files || !count($files))
                    continue;

                foreach ($files as $file) {
                    $full_file_name = AVREG_VIDEO_DIR . $dir1 . '/' . $dir2 .
                                              '/' . $cam_name . '/' . $file;
                    $file_stamp = get_video_file_timestamp($full_file_name);
                    $video_list[$file_stamp] = $full_file_name;
                }
            }
        }

        ksort($video_list, SORT_NUMERIC);
        $video_list_finished = [];
        foreach ($video_list as $time => $file) {
            $video_list_finished[] = ['time' => $time, 'file' => $file];
        }
        return $video_list_finished;
    }


    function video_files_from_archive($start_time, $duration, $cam_num)
    {
        $end_time = $start_time + $duration;
        $all_video_list = $this->sorted_video_list($cam_num);
        if (!$all_video_list) {
            $this->log->err("can't get camera %s videos\n", $cam_num);
            return false;
        }

        if (!count($all_video_list))
            return ['video_list' => [], 'incomplete' => true];

        // find start index
        $start_file_index = 0;
        $start_file_detected = false;
        foreach ($all_video_list as $file_index => $file_info) {
            if ($file_info['time'] < $start_time)
                continue;

            if ($file_info['time'] == $start_time) {
                $start_file_detected = true;
                $start_file_index = $file_index;
                break;
            }
            $start_file_detected = true;
            $start_file_index = ($file_index > 0) ? $file_index - 1 : 0;
            break;
        }

        if (!$start_file_detected)
            return ['video_list' => [], 'incomplete' => true];

        // find end index
        $end_file_index = -1;
        $list_incomplete = true;
        foreach ($all_video_list as $file_index => $file_info) {
            if ($file_info['time'] < $end_time)
                continue;

            if ($file_info['time'] == $end_time) {
                $end_file_index = $file_index;
                break;
            }

            $end_file_index = ($file_index > 0) ? $file_index - 1 : 0;
            $list_incomplete = false;
            break;
        }

        if ($list_incomplete)
            $end_file_index = ($file_index > 0) ? $file_index - 1 : 0;

        $video_list = [];
        for ($i = $start_file_index; $i <= $end_file_index; $i++)
            $video_list[] = $all_video_list[$i];

        printf("start file = %s\n", $all_video_list[$start_file_index]['file']);
        printf("start interval time = %s\n", $start_time);
        if ($end_file_index != -1)
            printf("end file = %s\n", $all_video_list[$end_file_index]['file']);
        else
            printf("end not found\n");

        return ['video_list' => $video_list, 'incomplete' => $list_incomplete];
    }


    function current_video_file($cam_name)
    {
        $avreg_pids = get_pid_list_by_command('/usr/sbin/avregd');
        if (!is_array($avreg_pids)) {
            $this->log->err("Can't get avreg PID\n");
            return -1;
        }

        if (count($avreg_pids) > 1) {
            $this->log->err("The a lot of avreg processes\n");
            return -1;
        }

        $avreg_pid = $avreg_pids[0];
        if (!$avreg_pid) {
            $this->log->err("Incorrect avreg PID\n");
            return -1;
        }

        $dir = sprintf("/proc/%d/fd/", $avreg_pid);
        // attempt three times
        for ($i = 0; $i < 3; $i++) {
            $files = scandir($dir);
            foreach ($files as $soft_link) {
                @$file = readlink($dir . $soft_link);
                preg_match(sprintf('/%s/', addcslashes(AVREG_VIDEO_DIR, '/')), $file, $mathes);
                 if (!isset($mathes[0]) || !trim($mathes[0]))
                     continue;
                 if (!strstr($file, $cam_name))
                     continue;

                 return $file;
             }
             sleep(1);
        }
        return false;
    }

    function video_files($start_time_stamp, $duration, $cam_name)
    {
        $end_time = $start_time_stamp + $duration;
        $ret = $this->video_files_from_archive($start_time_stamp, $duration, $cam_name);
        if (!$ret) {
            $this->log->err("can't get videos from archive\n");
            return false;
        }
        $video_list = $ret['video_list'];
        if (!$ret['incomplete'])
            return $video_list;

        $last_file = "";
        while(1) {
            $file = $this->current_video_file($cam_name);
            if (!$file) {
                $this->log->err("avreg can't recording video file now\n");
                return false;
            }

            if ($file == $last_file) {
                printf("wait\r");
                sleep(1);
                continue;
            }

            $last_file = $file;
            $file_time = $this->video_file_timestamp($file);
            if ($file_time > $end_time)
                break;

            $video_list[] = ['time' => $file_time, 'file' => $file];
        }

        return $video_list;
    }
}

function avreg()
{
    static $avreg = NULL;

    if ($avreg)
        return $avreg;

    $avreg = new Avreg;
    return $avreg;
}
