<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once 'common_lib.php';

define("WELL_PUMP_TIME_FILE", "/tmp/well_pump_time");

class Well_pump {
    function __construct() {
        $this->log = new Plog('sr90:Well_pump');
    }

    function start()
    {
        file_put_contents(WELL_PUMP_TIME_FILE, time());
        iop('water_pump')->up();
        $this->log->info("Well pump started");
    }

    function stop()
    {
        unlink_safe(WELL_PUMP_TIME_FILE);
        iop('water_pump')->down();
        $this->log->info("Well pump stoped");
    }

    function stat()
    {
        $duration = 0;
        if (file_exists(WELL_PUMP_TIME_FILE)) {
            $enable_time = file_get_contents(WELL_PUMP_TIME_FILE);
            if ($enable_time)
                $duration = time() - $enable_time;
        }
        return ['state' => iop('water_pump')->state()[0],
                'duration' => $duration];
    }
}

class Well_pump_io_handler implements IO_handler {
    function name() {
        return "well_pump";
    }

    function trigger_ports() {
        return ['do_start_stop' => ['RP_water_pump_button' => 1,
                                    'workshop_water_pump_button' => 1],
                'do_pressurization' => ['water_low_pressure' => 1]];
    }

    function do_start_stop($port, $state)
    {
        if (guard()->state() == 'ready') {
            run_cmd("./image_sender.php current", TRUE);
            tn()->send_to_msg("Ктото нажал на кнопку подачи воды");
            return;
        }

        $stat = well_pump()->stat();

        $duration = $stat['duration'];
        if (!$duration) {
            well_pump()->start();
            well_pump()->log->info("Water pump enabled\n");
            return;
        }

        if ($duration < 2)
            return;

        well_pump()->stop();
        well_pump()->log->info("Water pump disabled\n");
    }

    function do_pressurization($port, $state) {
        if ($state) {
            well_pump()->start();
            return;
        }
    }
}

function well_pump()
{
    static $well_pump = NULL;

    if ($well_pump)
        return $well_pump;

    $well_pump = new Well_pump;
    return $well_pump;
}


class Well_pump_cron_events implements Cron_events {
    function __construct()
    {
        $this->log = new Plog('sr90:Well_pump_cron');
    }

    function name()
    {
        return "well_pump";
    }

    function interval()
    {
        return "min";
    }

    function do()
    {
        $stat = well_pump()->stat();
        $duration = $stat['duration'];
        if ($duration < (30))
            return;

        if (iop('water_low_pressure')->state()[0] == 1)
            return;

        well_pump()->stop();
        $this->log->info("Well pump is stopped by timeout.");
    }
}

