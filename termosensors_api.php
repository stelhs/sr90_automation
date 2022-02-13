<?php
require_once 'io_api.php';


class Termo_sensor {
    function __construct($name) {
        $this->log = new Plog(sprintf('sr90:Termosensor_%s', $name));
        $this->_name = $name;
        $this->_descr = conf_termosensors()[$name]['description'];
        $this->_board = io()->board(conf_termosensors()[$name]['io']);
        $this->_addr = conf_termosensors()[$name]['addr'];
    }

    function t() {
        $ret = $this->_board->temperature($this->_addr);
        if ($ret[0])
            return NULL;
        return $ret[1];
    }

    function name() {
        return $this->_name;
    }

    function description() {
        return $this->_descr;
    }

    function addr() {
        return $this->_addr;
    }

    function board() {
        return $this->_board;
    }
}

class Termosensors {
    function __construct() {
        $this->_list = [];
        foreach (conf_termosensors() as $name => $row)
            $this->_list[] = new Termo_sensor($name);
    }

    function by_name($name) {
        foreach ($this->_list as $s) {
            if ($s->name() == $name)
                return $s;
        }
        return NULL;
    }

    function by_addr($addr) {
        foreach ($this->_list as $s) {
            if ($s->addr() == $addr)
                return $s;
        }
        return NULL;
    }

    function list() {
        return $this->_list;
    }

    function list_by_board_name($board_name) {
        $list = [];
        foreach ($this->_list as $t) {
            if ($t->_board->name() == $board_name)
                $list[] = $t;
        }
        return $list;
    }
}

function termosensors()
{
    static $termosensors = NULL;
    if (!$termosensors)
        $termosensors = new Termosensors;

    return $termosensors;
}

function termosensor($name) {
    return termosensors()->by_name($name);
}


