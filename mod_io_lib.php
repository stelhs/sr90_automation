<?php

require_once '/usr/local/lib/php/os.php';

class Mod_io {
    private $db;
    private $debug_output_states = [];
    
    function __construct($db) {
        $this->db = $db;
    }
    
    private function send_cmd($cmd)
    {
        $result = "";
        $fd = stream_socket_client("unix://" . conf_io()['socket_file'], $errno, $errstr, 30);
        if (!$fd)
            return -EBUSY;
           
        fwrite($fd, $cmd);
        while (!feof($fd))
            $result .= fgets($fd, 1024);
        fclose($fd);
        
        return trim($result);
    }
    
    
    public function relay_set_state($port, $state)
    {
        if (DISABLE_HW) {
            printf("io.relay_set_state %d to %d\n", $port, $state);
            $this->debug_output_states[$port] = $state;
            $this->db->insert('io_output_actions', array('port' => $port,
                                                         'state' => $state));
            return '0';
        }

        $ret = $this->send_cmd(sprintf("relay_set %d %d\n", $port, $state));
        if ($ret == "ok") {
            $this->db->insert('io_output_actions', array('port' => $port,
                                                         'state' => $state));
            return 0;
        }

        msg_log(LOG_ERR, sprintf("can't set relay state: %s\n", $ret));
        return -EBUSY;
    }

    public function relay_get_state($port)
    {
        if (DISABLE_HW) {
            printf("io.relay_get_state %d\n", $port);
            return isset($this->debug_output_states[$port]) ? $this->debug_output_states[$port] : '0'; 
        }

        $ret = $this->send_cmd(sprintf("relay_get %d\n", $port));
        if ($ret == "0" || $ret == "1")
            return $ret;

        msg_log(LOG_ERR, sprintf("can't get relay state: %s\n", $ret));
        return -EBUSY;
    }
    
    public function input_get_state($port)
    {
        if (DISABLE_HW) {
            printf("io.input_get_state %d\n", $port);
            return '0';
        }

        $ret = $this->send_cmd(sprintf("input_get %d\n", $port));
        if ($ret == "0" || $ret == "1")
            return $ret;
            
        msg_log(LOG_ERR, sprintf("can't get input state: %s\n", $ret));
        return -EBUSY;
    }
    
    public function wdt_reset()
    {
        $this->send_cmd("wdt_reset\n");
    }
    
    public function wdt_on()
    {
        $ret = $this->send_cmd("wdt_on\n");
        if ($ret == "ok")
            return 0;
            
        msg_log(LOG_ERR, sprintf("can't wdt on: %s\n", $ret));
        return -EBUSY;    
    }
    
    public function wdt_off()
    {
        $ret = $this->send_cmd("wdt_off\n");
        if ($ret == "ok")
            return 0;
            
        msg_log(LOG_ERR, sprintf("can't wdt off : %s\n", $ret));
        return -EBUSY;    
    }
}

