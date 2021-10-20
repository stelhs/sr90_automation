#!/usr/bin/php
<?php
// DEPRICATED
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'config.php';
require_once 'modem3g.php';
require_once 'telegram_lib.php';


function print_help()
{
    global $argv;
    $utility_name = $argv[0];
    pnotice("Usage: $utility_name <command> <args>\n" .
             "\tcommands:\n" .
             "\t$utility_name alarm <alarm_id> - Send all cameras photos associated with alarm_id to sr38.org and send links to Telegram\n" .
             "\t\tExample:\n" .
             "\t\t\t $utility_name alarm 27\n" .
             "\t$utility_name current [chat_id] - Request current camera photos and send links to Telegram. Messages send to all chats If chat_id is null\n" .
             "\t\tExample:\n" .
             "\t\t\t $utility_name current\n" .
    "\n\n");
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
        $alarm_id = $argv[2];pnot
        // copy images to sr38.org
        $ret = run_cmd(sprintf('scp %s/%d_*.jpeg stelhs@sr38.org:/storage/www/plato/alarm_img/',
                               conf_guard()['alarm_snapshot_dir'], $alarm_id));
        perror("scp to sr38.org: %s\n", $ret['log']);

        foreach (conf_guard()['video_cameras'] as $cam) {
            $msg = sprintf("Камера %d:\n http://sr38.org/plato/alarm_img/%d_cam_%d.jpeg",
                           $cam['id'], $alarm_id, $cam['id']);
            tn()->send_to_alarm($msg);
        }
        goto out;

    case 'current':
        $chat_id = isset($argv[2]) ? $argv[2] : 0;
        $content = file_get_contents('http://sr38.org/plato/?no_view');
        $ret = json_decode($content, true);
        if ($ret === NULL) {
            $rc = -1;
            $msg = sprintf("Не удалось получить изображение с камер: %s",
                           $content);
            tn()->send_to_admin($msg);
            perror("can't getting images\n");
            goto out;
        }

        foreach ($ret as $cam_num => $file) {
            if ($chat_id) {
                $msg = sprintf("Камера %d:\n %s", $cam_num, $file);
                telegram()->send_message($chat_id, $msg);
                continue;
            }
            $msg = sprintf("Камера %d:\n %s", $cam_num, $file);
            tn()->send_to_msg($msg);
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
