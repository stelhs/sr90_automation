<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';

require_once 'common_lib.php';
require_once 'config.php';

class Dvr {
    function __construct() {
        $this->log = new Plog("sr90:dvr");
        $this->cameras = [];
        foreach (conf_dvr()['cameras'] as $cam) {
            $this->cameras[$cam['name']] = new Camera($cam['name'],
                                                      $cam['desc'],
                                                      $cam['rtsp']);
        }
    }

    function cam($cname) {
        if (!isset($this->cameras[$cname]))
            return NULL;
        return $this->cameras[$cname];
    }

    function cams() {
        return $this->cameras;
    }

    function start()
    {
        foreach ($this->cameras as $cam)
            $cam->start();
    }

    function stop()
    {
        foreach ($this->cameras as $cam)
            $cam->stop();
    }

    function size()
    {
        $size = 0;
        foreach ($this->cameras as $cam)
            $size += $cam->size();
        return $size;
    }

    function duration()
    {
        $row = db()->query('select UNIX_TIMESTAMP(created) as start from videos ' .
                           'order by id asc limit 1');
        if ($row == NULL)
            return 0;
        if (!is_array($row) or $row < 0 or !isset($row['start'])) {
            $this->log->err("Can't calculate recording duration");
            return -1;
        }
        $start = $row['start'];

        $row = db()->query('select UNIX_TIMESTAMP(created) as end from videos ' .
                           'order by id desc limit 1');
        if ($row == NULL)
            return 0;
        if (!is_array($row) or $row < 0 or !isset($row['end'])) {
            $this->log->err("Can't calculate recording duration");
            return -1;
        }

        $end = $row['end'];
        return $end - $start;
    }

    function stat_text()
    {
        $tg = '';
        $sms = '';

        $all_recording = true;
        $total_size = 0;
        foreach (dvr()->cams() as $cam) {
            $size = $cam->size();
            $size_gb = $size / (1024*1024*1024);
            $tg .= sprintf("Камера '%s': %s, %.1fGb, %.1f часов\n",
                $cam->description(), $cam->is_recording() ? 'пишется' : 'не пишется',
                $size_gb, $cam->duration() / 3600);
            $total_size += $size;
            if (!$cam->is_recording())
                $all_recording = false;
        }
        $tg .= sprintf("Покрыто записями последние: %.1f суток\n",
                       dvr()->duration() / 3600 / 24);
        $tg .= sprintf("Занимает: %.1fGb\n", $total_size / (1024*1024*1024));
        $tg .= sprintf("Размер накопителя: %.1fGb\n", conf_dvr()['storage']['max_size_gb']);

        $sms .= $all_recording ? 'dvr:ok' : 'dvr:problem';
        return [$tg, $sms];
    }

}


class Camera {
    function __construct($cname, $desc, $rtsp) {
        $this->log = new Plog(sprintf("sr90:camera_%s", $cname));
        $this->cname = $cname;
        $this->desc = $desc;
        $this->rtsp = $rtsp;
        $this->rec_dir = conf_dvr()['storage']['dir'];
        $this->file_duration = conf_dvr()['video_file_duration'];
    }

    function name() {
        return $this->cname;
    }

    function description() {
        return $this->desc;
    }

    function rtsp() {
        return $this->rtsp;
    }

    function pid_file_name() {
        return sprintf('%s/camera_%s_pid', PID_DIR, $this->cname);
    }

    function restart_file_name() {
        return sprintf('%s/camera_%s_started', PID_DIR, $this->cname);
    }

    function pid() {
        $fname = $this->pid_file_name($this->cname);
        if (!file_exists($fname))
            return 0;
        $pid = file_get_contents($fname);
        return $pid;
    }

    function start()
    {
        if ($this->is_recording()) {
            $this->log->warn("Can't starting camera '%s' recording. " .
                            "Recording already was started.\n", $this->cname);
            return;
        }

        file_put_contents($this->restart_file_name(), '');

        $cmd = sprintf("./cam_rec.sh %s %s '/usr/local/bin/openRTSP " .
                       "-D 20 -K -b 1000000 -4 -P %d -c -t -f 25 " .
                       "-F %s -N %s -j %s %s'",
                       $this->pid_file_name(),
                       $this->restart_file_name(),
                       $this->file_duration,
                       $this->rec_dir, $this->cname,
                       $this->pid_file_name(), $this->rtsp);

        run_cmd($cmd, true, '', false);
        $this->log->info("camera '%s' recording has started", $this->cname);
    }

    function stop() {
        if (!$this->is_recording()) {
            $this->log->warn("Can't stopping camera '%s' recording. " .
                            "Recording already was stopped.\n", $this->cname);
            return;
        }
        unlink_safe($this->restart_file_name());
        run_cmd(sprintf('kill %d', $this->pid()));
        $this->log->info("camera '%s' recording has stopped", $this->cname);
    }

    function is_recording() {
        return $this->pid() ? true : false;
    }

    function size() {
        $row = db()->query('select sum(file_size) as size '.
                    'from videos where cam_name = "%s"',
                    $this->cname);
        if ($row == NULL)
            return 0;
        if (!is_array($row) or $row < 0 or !isset($row['size'])) {
            $this->log->err("Can't calculate recording size for camera %s", $this->cname);
            return -1;
        }
        return $row['size'];
    }

    function duration()
    {
        $row = db()->query('select sum(duration) as sum from videos ' .
                           'where cam_name = "%s" and file_size is not NULL',
                           $this->cname);
        if ($row == NULL)
            return 0;

        if (!is_array($row) or $row < 0 or !isset($row['sum'])) {
            $this->log->err("Can't calculate recording duration for camera %s", $this->cname);
            return -1;
        }

        return $row['sum'];

        $row = db()->query('select UNIX_TIMESTAMP(created) as start from videos ' .
                           'where cam_name = "%s" order by id asc limit 1',
                           $this->cname);
        if ($row == NULL)
            return 0;
        if (!is_array($row) or $row < 0 or !isset($row['start'])) {
            $this->log->err("Can't calculate recording duration for camera %s", $this->cname);
            return -1;
        }
        $start = $row['start'];

        $row = db()->query('select UNIX_TIMESTAMP(created) as end from videos ' .
                           'where cam_name = "%s" order by id desc limit 1',
                           $this->cname);
        if ($row == NULL)
            return 0;
        if (!is_array($row) or $row < 0 or !isset($row['end'])) {
            $this->log->err("Can't calculate recording duration for camera %s", $this->cname);
            return -1;
        }

        $end = $row['end'];

        return $end - $start;
    }

    function make_screenshot()
    {
        $fname = sprintf('%s_%s.jpeg',
                         $this->cname,
                         date("Y-m-d_H_m_s"));

        $cmd = sprintf("ffmpeg -i %s -filter:v scale=1920:-1 -vframes 1 %s/%s",
                       $this->rtsp,
                       conf_dvr()['storage']['snapshot_dir'],
                       $fname);
        $ret = run_cmd($cmd);
        if ($ret['rc']) {
            $this->log->warn("Can't make screenshot for camera '%s': %s",
                             $this->cname, $ret['log']);
            return NULL;
        }
        return $fname;
    }
}

function dvr()
{
    static $dvr = NULL;
    if (!$dvr)
        $dvr = new Dvr;

    return $dvr;
}

class Dvr_cron_events implements Cron_events {
    function name() {
        return "dvr";
    }

    function interval() {
        return "5min";
    }

    function __construct() {
        $this->log = new Plog("sr90:dvr_cron");
    }

    function do() {
        $this->do_check_recording();
        $this->do_check_file_size();
        $this->do_remove();
    }

    function do_check_recording()
    {
        $str = '';
        foreach (dvr()->cams() as $cam) {
            if ($cam->is_recording())
                continue;

            $str .= sprintf("Запись камеры '%s' остановлена\n", $cam->name());
        }
        if ($str)
            tn()->send_to_admin($str);
    }

    function do_check_file_size()
    {
        $rows = db()->query_list('select * from videos where file_size is not null ' .
                                 'and file_size < %d', conf_dvr()['min_file_size']);
        if ($rows == NULL)
            return;

        if (!is_array($rows) or $rows < 0) {
            $this->log->err("Can't select from videos for check file size");
            return;
        }

        $str = '';
        $cams = [];
        foreach ($rows as $row) {
            if (!isset($cams[$row['cam_name']]))
                $cams[$row['cam_name']] = 0;
            $cams[$row['cam_name']] ++;
            $str .= sprintf("Файл %s имеет размер %.1fKb\n",
                            $row['fname'], $row['file_size'] / 1024);
            unlink(conf_dvr()['storage']['dir'] . $row['fname']);
            db()->query('delete from videos where id = %d', $row['id']);
        }
        tn()->send_to_admin("Обнаруженны попорченные видео файлы:\n%s", $str);

        foreach ($cams as $cam_name => $cnt) {
            if (!$cnt)
                continue;
            $cam = dvr()->cam($cam_name);
            $cam->stop();
            sleep(1);
            $cam->start();
        }
    }

    function remove_empty_sub_dirs($base_dir, $subdir)
    {
        $dir = $subdir;
        while ($dir) {
            $full_dir = sprintf("%s/%s", $base_dir, $dir);
            if (!is_dir_empty($full_dir))
                return;

            rmdir($full_dir);
            $dir = dirname($dir);
        }
    }

    function do_remove()
    {
        $gb = (1024 * 1024 * 1024);
        $size = dvr()->size();
        $size_gb = $size / $gb;
        $max_size = conf_dvr()['storage']['max_size_gb'] * $gb;

        $this->log->info("video storage size: %.1fGb, max_storage_size: %.1fGb,\n",
                         $size_gb, conf_dvr()['storage']['max_size_gb']);

        if ($size < $max_size)
            return;

        $size_to_delete = $size - $max_size;
        $size_to_delete_gb = $size_to_delete / $gb;
        $this->log->info("video storage size: %.1fGb, need to delete size: %.1fGb,\n",
                         $size_gb, $size_to_delete_gb);

        $cnt = 0;
        while($size_to_delete > 0) {
            $cnt ++;
/*            $row = db()->query('select id, fname, file_size from videos ' .
                               'where file_size is not NULL ' .
                               'order by id asc limit 1');*/
            $row = db()->query('select id, fname, file_size from videos ' .
                               'where created < (now() - interval 5 minute) ' .
                               'order by id asc limit 1');
            if ($row == NULL)
                break;

            if (!is_array($row) or $row < 0 or !isset($row['id'])) {
                $this->log->err("Can't select video file");
                return -1;
            }

            $fname = sprintf("%s/%s", conf_dvr()['storage']['dir'], $row['fname']);
            $this->log->info("remove %s\n", $fname);
            unlink($fname);
            $this->remove_empty_sub_dirs(conf_dvr()['storage']['dir'],
                                         dirname($row['fname']));

            db()->query('delete from videos where id = %d', $row['id']);
            $size_to_delete -= $row['file_size'];
        }
        $this->log->info("%d files were removed\n", $cnt);
    }
}


class Dvr_handler implements Http_handler {
    function name() {
        return "dvr";
    }

    function requests() {
        return ['/dvr/screenshots' => ['method' => 'GET',
                                       'handler' => 'screenshots',
                            ]];
    }

    function __construct() {
        $this->log = new Plog('sr90:Dvr_io_handler');
    }

    function screenshots($args, $from, $request)
    {
        $stat = [];
        $stat['cameras'] = [];
        $index = 0;
        foreach (dvr()->cams() as $cam) {
            $fname = $cam->make_screenshot();
            if (!$fname)
                continue;

            $url = sprintf("%s/%s", conf_dvr()['storage']['http_snapshot_dir'], $fname);
            $info = [];
            $info['desc'] = $cam->description();
            $info['index'] = ++$index;
            $info['screenshot'] = $url;
            $stat['cameras'][$cam->name()] = $info;
        }

        $stat['status'] = count($stat['cameras']) ? 'ok' : 'error';
        $stat['status'] = 'ok';
        return json_encode($stat);
    }
}


