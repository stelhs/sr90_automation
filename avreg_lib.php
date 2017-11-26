<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';


function get_video_file_timestamp($full_file_name)
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

function get_sorted_video_list($cam_name)
{
    $video_list = [];
    $avreg_dir = "/var/spool/avreg/";
    $dirs1 = get_list_subdirs($avreg_dir);
    if (!$dirs1) {
        perror("Can't find directory: %s\n", $avreg_dir);
        return false;
    }

    if (!count($dirs1)) {
        perror("Directory %s is empty: %s\n", $avreg_dir);
        return [];
    }

    foreach ($dirs1 as $dir1) {
        $dirs2 = get_list_subdirs($avreg_dir . $dir1 . '/');
        if (!$dirs2 || !count($dirs2))
            continue;

        foreach ($dirs2 as $dir2) {
            $files = get_list_files($avreg_dir . $dir1 . '/' . $dir2 . '/' . $cam_name . '/');
            if (!$files || !count($files))
                continue;

            foreach ($files as $file) {
                $full_file_name = $avreg_dir . $dir1 . '/' . $dir2 .
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


function get_video_files_from_archive($start_time, $duration, $cam_num)
{
    $end_time = $start_time + $duration;
    $all_video_list = get_sorted_video_list($cam_num);
    if (!$all_video_list) {
        perror("can't get camera %s videos\n", $cam_num);
        return false;
    }

    if (!count($all_video_list))
        return ['video_list' => [], 'incomplete' => true];

    // find start index
    $start_file_index = 0;
    foreach ($all_video_list as $file_index => $file_info) {
        if ($file_info['time'] < $start_time)
            continue;

        if ($file_info['time'] == $start_time) {
            $start_file_index = $file_index;
            break;
        }

        $start_file_index = ($file_index > 0) ? $file_index - 1 : 0;
        break;
    }

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
        $end_file_index = $file_index;

    $video_list = [];
    for ($i = $start_file_index; $i <= $end_file_index; $i++)
        $video_list[] = $all_video_list[$i];

/*    printf("start time = %s\n", $all_video_list[$start_file_index]['file']);
    if ($end_file_index != -1)
        printf("end time = %s\n", $all_video_list[$end_file_index]['file']);
    else
        printf("end not found\n");
*/
    return ['video_list' => $video_list, 'incomplete' => $list_incomplete];
}


function get_current_video_file($cam_name)
{
    $avreg_pids = get_pid_list_by_command('avregd');
    if (!is_array($avreg_pids)) {
        perror("Can't get avreg PID\n");
        return -1;
    }

    if (count($avreg_pids) > 1) {
        perror("The lost of avreg processes\n");
        return -1;
    }

    $avreg_pid = $avreg_pids[0];
    if (!$avreg_pid) {
        perror("Incorrect avreg PID\n");
        return -1;
    }

    $dir = sprintf("/proc/%d/fd/", $avreg_pid);
    $files = scandir($dir);
    foreach ($files as $soft_link) {
        @$file = readlink($dir . $soft_link);
        preg_match('/\/var\/spool\/avreg/', $file, $mathes);
        if (!isset($mathes[0]) || !trim($mathes[0]))
            continue;
        if (!strstr($file, $cam_name))
            continue;

        return $file;
    }
    return false;
}

function get_video_files($start_time_stamp, $duration, $cam_name)
{
    $end_time = $start_time_stamp + $duration;
    $ret = get_video_files_from_archive($start_time_stamp, $duration, $cam_name);
    if (!$ret) {
        perror("can't get videos from archive\n");
        return false;
    }
    $video_list = $ret['video_list'];
    if (!$ret['incomplete'])
        return $video_list;

    $last_file = "";
    while(1) {
        $file = get_current_video_file($cam_name);
        if (!$file) {
            perror("avreg can't recording video file now\n");
            return false;
        }

        if ($file == $last_file) {
            printf("wait\r");
            sleep(1);
            continue;
        }

        $last_file = $file;
        $file_time = get_video_file_timestamp($file);
        if ($file_time > $end_time)
            break;

        $video_list[] = ['time' => $file_time, 'file' => $file];
    }

    return $video_list;
}

