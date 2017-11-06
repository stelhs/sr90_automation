<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once 'common_lib.php';

class Httpio {
    private $debug_output_states = [];
    public $ip_addr;
    public $tcp_port;
    public $io;

    function __construct($ip_addr, $tcp_port)
    {
        $this->ip_addr = $ip_addr;
        $this->tcp_port = $tcp_port;
    }

    private function send_cmd($cmd, $args)
    {
        $query = "";
        $separator = "";
        foreach ($args as $var => $val) {
            $query .= $separator . sprintf("%s=%s", $var, $val);
            $separator = "&";
        }

        $http_request = sprintf("http://%s:%d/usio/%s?%s",
                                $this->ip_addr,
                                $this->tcp_port,
                                $cmd,
                                $query);

        $content = file_get_contents($http_request);
        if (!$content)
            return ['status' => 'error',
            		'reason' => 'connection error'];

        $ret_data = json_decode($content, true);
        if (!$ret_data)
            return ['status' => 'error',
            		'reason' => 'json decode error'];
        return $ret_data;
    }

    public function relay_set_state($port, $state)
    {
        db()->insert('io_output_actions', ['io_name' => $this->io,
                                           'port' => $port,
                                           'state' => $state]);
        if (DISABLE_HW) {
            perror("FAKE: httpio.relay_set_state %s: %d to %d\n", $this->ip_addr, $port, $state);
            $this->debug_output_states[$this->ip_addr][$port] = $state;
            return '0';
        }

        $ret = $this->send_cmd("relay_set", ['port' => $port, 'state' => $state]);
        if ($ret['status'] == "ok")
            return 0;

        perror("Can't set relay state over HTTP with address %s. HTTP server return error: %s\n",
                                                $this->ip_addr, $ret['reason']);
        return -EBUSY;
    }

    public function relay_get_state($port)
    {
        if (DISABLE_HW) {
            perror("FAKE: httpio.relay_get_state %s: %d\n", $this->ip_addr, $port);
            return isset($this->debug_output_states[$this->ip_addr][$port]) ? $this->debug_output_states[$this->ip_addr][$port] : '0';
        }

        $ret = $this->send_cmd("relay_get", ['port' => $port]);
        if ($ret['status'] == "ok")
            return $ret['log'];

        perror("can't get relay state %s: %s\n", $this->ip_addr, $ret['reason']);
        return -EBUSY;
    }

    public function input_get_state($port)
    {
        if (DISABLE_HW) {
            perror("FAKE: httpio.input_get_state %d\n", $port);
            return '0';
        }

        $ret = $this->send_cmd("input_get", ['port' => $port]);
        if ($ret['status'] == "ok")
            return $ret['log'];

        perror("can't get input state %s: %s\n", $this->ip_addr, $ret['reason']);
        return -EBUSY;
    }
}

function httpio($name)
{
    static $httpio = NULL;

    if (!isset(conf_io()[$name])) {
        perror("I/O module %s is not found\n", $name);
        exit;
    }

    $ip_addr = conf_io()[$name]['ip_addr'];
    $tcp_port = conf_io()[$name]['tcp_port'];

    if ($httpio) {
        $httpio->io = $name;
        $httpio->ip_addr = $ip_addr;
        $httpio->tcp_port = $tcp_port;
        return $httpio;
    }

    $httpio = new Httpio($ip_addr, $tcp_port);
    $httpio->io = $name;
    return $httpio;
}

