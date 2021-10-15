<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once 'common_lib.php';


class Httpio {
    public $ip_addr;
    public $tcp_port;

    function __construct($name, $ip_addr, $tcp_port)
    {
        $this->ip_addr = $ip_addr;
        $this->tcp_port = $tcp_port;
        $this->name = $name;
    }

    private function send_cmd($cmd, $args)
    {
        $query = "";
        $separator = "";
        foreach ($args as $var => $val) {
            $query .= $separator . sprintf("%s=%s", $var, $val);
            $separator = "&";
        }

        $http_request = sprintf("http://%s:%d/io/%s?%s",
                                $this->ip_addr,
                                $this->tcp_port,
                                $cmd,
                                $query);
        dump($http_request);
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
        db()->insert('io_output_actions', ['io_name' => $this->name,
                                           'port' => $port,
                                           'state' => $state]);
        if (DISABLE_HW) {
            perror("FAKE: httpio.relay_set_state %s: %d to %d\n", $this->ip_addr, $port, $state);
            $ports = $this->read_fake_out_ports();
            $ports[$port] = $state;
            $this->write_fake_out_ports($ports);
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
            return $this->read_fake_out_ports()[$port];
        }

        $ret = $this->send_cmd("relay_get", ['port' => $port]);
        if ($ret['status'] == "ok")
                return $ret['state'];

        perror("can't get relay state %s: %s\n", $this->ip_addr, $ret['reason']);
        return -EBUSY;
    }

    public function input_get_state($port)
    {
        if (DISABLE_HW) {
            $ports = $this->read_fake_in_ports();
            perror("FAKE: httpio.input_get_state %d\n", $port);
            return $ports[$port];
        }

        $ret = $this->send_cmd("input_get", ['port' => $port]);
        if ($ret['status'] == "ok")
            return $ret['state'];

        perror("can't get input state %s: %s\n", $this->ip_addr, $ret['reason']);
        return -EBUSY;
    }

    private function read_fake_in_ports()
    {
        $io_name = $this->name;
        $ports = [];
        for ($i = 1; $i <= conf_io()[$io_name]['in_ports']; $i++)
            $ports[$i] = 0;

        @$str = file_get_contents(sprintf('fake_in_ports_%s.txt', $io_name));
        if (!$str)
            return $ports;

        $rows = string_to_array($str, "\n");
        for ($i = 1; $i <= conf_io()[$io_name]['in_ports']; $i++)
            @$ports[$i] = $rows[$i - 1];

        return $ports;
    }

    private function read_fake_out_ports()
    {
        $io_name = $this->name;
        $ports = [];
        for ($i = 1; $i <= conf_io()[$io_name]['out_ports']; $i++)
            $ports[$i] = 0;

        @$str = file_get_contents(sprintf('fake_out_ports_%s.txt', $io_name));
        if (!$str)
            return $ports;

        $rows = string_to_array($str, "\n");
        for ($i = 1; $i <= conf_io()[$io_name]['out_ports']; $i++)
            @$ports[$i] = $rows[$i - 1];

        return $ports;
    }

    private function write_fake_out_ports($ports)
    {
        $io_name = $this->name;
        foreach ($ports as $num => $value)
            $ports[$num] = $value ? '1' : '0';

        $str = array_to_string($ports, "\n");
        file_put_contents(sprintf('fake_out_ports_%s.txt', $io_name), $str);
    }
}

function httpio($name)
{
    $httpio = NULL;

    if (!isset(conf_io()[$name])) {
        perror("I/O module %s is not found\n", $name);
        exit;
    }

    $ip_addr = conf_io()[$name]['ip_addr'];
    $tcp_port = conf_io()[$name]['tcp_port'];

    $httpio = new Httpio($name, $ip_addr, $tcp_port);
    return $httpio;
}


class Httpio_input_port {
    function __construct($httpio, $io_port)
    {
        $this->httpio = $httpio;
        $this->io_port = $io_port;
    }

    public function get()
    {
        return $this->httpio->input_get_state($this->io_port);
    }
}


class Httpio_output_port {
    function __construct($httpio, $io_port)
    {
        $this->httpio = $httpio;
        $this->io_port = $io_port;
    }

    public function set($state)
    {
        return $this->httpio->relay_set_state($this->io_port, $state);
    }

    public function get()
    {
        return $this->httpio->relay_get_state($this->io_port);
    }
}


function httpio_port($port_info)
{
    $httpio = httpio($port_info['io']);
    if (isset($port_info['in_port']))
        return new Httpio_input_port($httpio, $port_info['in_port']);

    if (isset($port_info['out_port']))
        return new Httpio_output_port($httpio, $port_info['out_port']);
}

