#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'config.php';
require_once 'modem3g.php';
$utility_name = $argv[0];


function print_help()
{
    global $utility_name;
    echo "Usage: $utility_name <command> <args>\n" . 
    	     "\tcommands:\n" .

    "\n\n";
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
        // copy images to sr38.org
        $ret = run_cmd(sprintf('scp %s/%d_*.jpeg stelhs@sr38.org:/var/www/plato/alarm_img/', 
                               conf_guard()['alarm_snapshot_dir'], $alarm_id));
        printf("scp to sr38.org: %s\n", $ret['log']);

        foreach (conf_guard()['video_cameras'] as $cam) {
            $ret = run_cmd(sprintf("./telegram.php msg_send_all 'Камера %d:\n http://sr38.org/plato/alarm_img/%d_cam_%d.jpeg'", 
                                   $cam['id'], $alarm_id, $cam['id']));
            printf("send URL to telegram: %s\n", $ret['log']);
        }
        goto out;

    case 'current':
        $content = file_get_contents('http://sr38.org/plato/?no_view');
        $ret = json_decode($content, true);
        if ($ret === NULL) {
            $rc = -1;
            run_cmd(sprintf("./telegram.php msg_send_all 'Не удалось получить изобрадение с камер: %s'", 
                                   $content));
            printf("can't getting images: %s\n", $ret);
            goto out;
        }

        foreach ($ret as $cam_num => $file) {
            $ret = run_cmd(sprintf("./telegram.php msg_send_all 'Камера %d:\n %s'", 
                                   $cam_num, $file));
            printf("send URL to telegram: %s\n", $ret['log']);
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
}
    