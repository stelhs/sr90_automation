<?php

require_once '/usr/local/lib/php/os.php';

/* usio - Unix Socket Input/Output */
class Usio {
    private $debug_output_states = [];

    function __construct()
    {
        $this->log = new Plog('sr90:Usio');
    }

    private function send_cmd($cmd)
    {
        $result = "";
        $fd = stream_socket_client("unix://" . conf_local_io()['socket_file'], $errno, $errstr, 30);
        if (!$fd) {
            $this->log->err('send_cmd(): cant stream_socket_client: %s\n', $fd);
            return -EBUSY;
        }

        fwrite($fd, $cmd);
        while (!feof($fd))
            $result .= fgets($fd, 1024);
        fclose($fd);

        return trim($result);
    }

    public function relay_set_state($port, $state)
    {
        if (DISABLE_HW) {
            $this->log->err("FAKE: io.relay_set_state %d to %d\n", $port, $state);
            $this->debug_output_states[$port] = $state;
            return '0';
        }

        $ret = $this->send_cmd(sprintf("relay_set %d %d\n", $port, $state));
        if ($ret == "ok")
            return 0;

        $this->log->err("can't set relay state %d %d: %s", $port, $state, $ret);
        return -EBUSY;
    }

    public function relay_get_state($port)
    {
        if (DISABLE_HW) {
            perror("FAKE: io.relay_get_state %d\n", $port);
            return isset($this->debug_output_states[$port]) ? $this->debug_output_states[$port] : '0';
        }

        $ret = $this->send_cmd(sprintf("relay_get %d\n", $port));
        if ($ret == "0" || $ret == "1")
            return $ret;

        $this->log->err("can't get relay state %d ret: %s", $port, $ret);
        return -EBUSY;
    }

    public function input_get_state($port)
    {
        if (DISABLE_HW) {
            perror("FAKE: io.input_get_state %d\n", $port);
            return '0';
        }

        $ret = $this->send_cmd(sprintf("input_get %d\n", $port));
        if ($ret == "0" || $ret == "1")
            return $ret;

        $this->log->err("can't get input state %d: %s", $port, $ret);
        return -EBUSY;
    }

    public function wdt_reset()
    {
        $this->send_cmd("wdt_reset\n");
    }

    public function wdt_on()
    {
        $ret = $this->send_cmd("wdt_on\n");
        if ($ret == "ok") {
            $this->log->info("Watchdog enabled");
            return 0;
        }

        $this->log->err("can't wdt on: %s", $ret);
        return -EBUSY;
    }

    public function wdt_off()
    {
        $ret = $this->send_cmd("wdt_off\n");
        if ($ret == "ok") {
            $this->log->info("Watchdog disabled");
            return 0;
        }

        $this->log->err("can't wdt off: %s", $ret);
        return -EBUSY;
    }
}

function usio()
{
    static $usio = NULL;
    if ($usio)
        return $usio;

    $usio = new Usio();
    return $usio;
}

