<?php

require_once '/usr/local/lib/php/os.php';

class Mod_io {
    private $db;
    
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
        $ret = $this->send_cmd(sprintf("relay_get %d\n", $port));
        if ($ret == "0" || $ret == "1")
            return $ret;

        msg_log(LOG_ERR, sprintf("can't get relay state: %s\n", $ret));
        return -EBUSY;
    }
    
    public function input_get_state($port)
    {
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

