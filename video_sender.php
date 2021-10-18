#!/usr/bin/php
<?php
// DEPRICATED
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';

require_once 'config.php';
require_once 'avreg_lib.php';
require_once 'telegram_lib.php';



function print_help()
{
    global $argv;
    $utility_name = $argv[0];
    echo "Usage: $utility_name <command> <args>\n" .
             "\tcommands:\n" .

             "\t$utility_name alarm <alarm_id> - Send all camera videos associated with alarm_id to sr38.org and send links to Telegram\n" .
             "\t\tExample:\n" .
             "\t\t\t $utility_name alarm 27 timestamp\n" .

             "\t$utility_name by_timestamp <timestamp> <duration> [cam_list_ids] [telegram_id] - Send camera videos by timestamp to sr38.org and send links to Telegram\n" .
             "\t\tExample:\n" .
             "\t\t\t $utility_name by_timestamp 1544390409 5 1,2 186579253\n" .

    "\n\n";
}

function upload_cam_video($cam, $server_dir, $start_time, $duration, $prefix = "")
{
    $server_files = [];
    $video_files = avreg()->video_files($start_time, $duration, $cam['name']);
    if (!$video_files || !count($video_files)) {
        $msg = sprintf("Неудалось получить видеофайлы для камеры %s",
            $cam['name']);
        tn()->send_to_admin($msg);
        perror("Can't get videos for camera %s\n", $cam['name']);
        return -1;
    }
    $cnt = 0;
    foreach ($video_files as $file) {
        $cnt ++;
        if ($cnt > 15) {
            perror("To many video files\n");
            return -1;
        }
        dump($file);
        $server_filename = sprintf("%s_%d_%s", $prefix, $cam['id'], basename($file['file']));
        printf("server_filename = %s\n", $server_filename);

        $cmd = sprintf('scp %s stelhs@sr38.org:/storage/www/plato/%s/%s',
                       $file['file'], $server_dir, $server_filename);
        $ret = run_cmd($cmd);
        if ($ret['rc']) {
            $msg = sprintf("Неудалось загрузить видеофайл %s для камеры %s: %s",
                $file['file'], $cam['name'], $ret['log']);
            tn()->send_to_admin($msg);
            perror("Can't upload videos for camera %s: %s\n", $cam['name'], $ret['log']);
            continue;
        }

        $server_files[] = sprintf("http://sr38.org/plato/%s/%s",
                                  $server_dir, $server_filename);
    }
    return $server_files;
}

function main($argv)
{
    $rc = 0;
    if (!isset($argv[1])) {
        return -EINVAL;
    }

    $mode = $argv[1];

    switch ($mode) {
    case 'alarm':
        $alarm_id = $argv[2];
        $row = db()->query(sprintf('SELECT UNIX_TIMESTAMP(created) as alarm_time ' .
                                   'FROM guard_actions ' .
                                   'WHERE id = %d', $alarm_id));
        $alarm_timestamp = $row['alarm_time'];

        printf("alarm_id = %d\n", $alarm_id);
        printf("alarm_timestamp = %d\n", $alarm_timestamp);

        $msg = sprintf("Загружаю видео файлы по событию %d, ожидайте...", $alarm_id);
        tn()->send_to_alarm($msg);
        foreach(conf_guard()['video_cameras'] as $cam) {
            $server_video_urls = upload_cam_video($cam, 'alarm_video',
                                                  $alarm_timestamp - 10, 20,
                                                  $alarm_id);
            if (!is_array($server_video_urls))
                continue;
            $msg = "";
            foreach($server_video_urls as $url) {
                $msg .= sprintf("Видео запись события %d: Камера %d:\n %s\n",
                                $alarm_id, $cam['id'], $url);
            }
            if ($msg)
                tn()->send_to_alarm($msg);
        }
        $msg = sprintf("Процесс загрузки видео по событию %d завершен", $alarm_id);
        tn()->send_to_alarm($msg);
        goto out;

    case 'by_timestamp':
        $timestamp = $argv[2];
        $duration = $argv[3];
        $cam_list_id_comma = isset($argv[4]) ? $argv[4] : NULL;
        $chat_id = isset($argv[5]) ? $argv[5] : NULL;
        if (!$chat_id) {
            foreach (tn()->chats('admin') as $chat)
                break;
            $chat_id = $chat['chat_id'];
        }

        $cam_list_id = NULL;
        if ($cam_list_id_comma)
            $cam_list_id = split_string_by_separators($cam_list_id_comma, ',');

        printf("timestamp = %d\n", $timestamp);
        printf("duration = %d\n", $duration);
        printf("cam_list_id = \n");
        dump($cam_list_id);
        printf("chat_id = %d\n", $chat_id);

        foreach(conf_guard()['video_cameras'] as $cam) {
            if ($cam_list_id) {
                $found = FALSE;
                foreach($cam_list_id as $cam_id)
                    if ($cam_id == $cam['id'])
                        $found = TRUE;
                if (!$found)
                    continue;
            }

            $server_video_urls = upload_cam_video($cam, 'query_video',
                                                  $timestamp, $duration,
                                                  $timestamp);
            if (!is_array($server_video_urls))
                continue;
            $msg = "";
            foreach($server_video_urls as $url)
                $msg .= sprintf("Видео запись с камеры %d:\n %s\n",
                               $cam['id'], $url);
            if ($msg)
                tn()->send($chat_id, 0, $msg);
        }
        goto out;


    default:
        $rc = -EINVAL;
    }

out:
    return $rc;
}

$rc = main($argv);
if ($rc) {
    print_help();
    exit($rc);
}
