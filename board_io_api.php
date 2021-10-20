<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';

require_once 'common_lib.php';
require_once 'settings.php';

$log = new Plog('sr90:board_io');


class Board_io {
    public $ip_addr;
    public $tcp_port;
    public $name;
    private $log;

    function __construct($name)
    {
        $this->ip_addr = conf_io()[$name]['ip_addr'];
        $this->tcp_port = conf_io()[$name]['tcp_port'];
        $this->name = $name;
        $this->log = new Plog('sr90:Board_io');
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
        $content = file_get_contents($http_request);
        if (!$content) {
            $this->log->err("Null bytes received from addr: %s", $http_request);
            return ['status' => 'error',
            		'reason' => 'connection error'];
        }

        $ret_data = json_decode($content, true);
        if (!$ret_data) {
            $this->log->err("Can't decode JSON from addr: %s", $http_request);
            return ['status' => 'error',
            		'reason' => 'json decode error'];
        }
        return $ret_data;
    }

    public function relay_set_state($port, $state)
    {
        $pname = port_name_by_addr($this->name, 'out', $port);
        $row_id = db()->insert('io_events',
                                ['mode' => 'out',
                                 'port_name' => $pname,
                                 'io_name' => $this->name,
                                 'port' => $port,
                                 'state' => $state]);
        if (!$row_id)
            $this->log->err("relay_set_state(): Can't insert into io_events");

        $this->log->info("set %s -> %d\n",
                         port_str($pname, $this->name,
                                  'out', $port), $state);

        if (DISABLE_HW) {
            $ports = $this->read_fake_out_ports();
            $ports[$port] = $state;
            $this->write_fake_out_ports($ports);
            return 0;
        }

        if ($this->name == 'usio1') {
            $ret = usio()->relay_set_state($port, $state);
            if (!$ret)
                return 0;
            $reason = '';
        } else {
            $ret = $this->send_cmd("relay_set", ['port' => $port, 'state' => $state]);
            if ($ret['status'] == "ok")
                return 0;
            $reason = $ret['reason'];
        }

        $err = sprintf("Can't set %s: %d for %s over HTTP. " .
                        "HTTP server return error: '%s'\n",
                        port_str($pname, $this->name, 'out', $port), $state,
                        $this->ip_addr, $reason);
        $this->log->err($err);
        return $err;
    }

    public function relay_get_state($port)
    {
        $pname = port_name_by_addr($this->name, 'out', $port);
        if (DISABLE_HW) {
            $s = $this->read_fake_out_ports()[$port];
            $this->log->info("state %s -> %d\n",
                             port_str($pname, $this->name, 'out', $port), $s);
            return $s;
        }

        if ($this->name == 'usio1') {
            $s = usio()->relay_get_state($port);
            if ($s >= 0) {
                $this->log->info("state %s -> %d\n",
                                 port_str($pname, $this->name, 'out', $port), $s);
                return $s;
            }
            $reason = '';
        } else {
            $ret = $this->send_cmd("relay_get", ['port' => $port]);
            if ($ret['status'] == "ok") {
                $s = $ret['state'];
                $this->log->info("state %s -> %d\n",
                                 port_str($pname, $this->name, 'out', $port), $s);
                return $s;
            }
            $reason = $ret['reason'];
        }

        $err = sprintf("Can't get %s over HTTP. " .
                        "HTTP server return error: '%s'\n",
                        port_str($pname, $this->name, 'out', $port),
                        $reason);
        $this->log->err($err);
        return $err;
    }

    public function input_get_state($port)
    {
        $pname = port_name_by_addr($this->name, 'in', $port);
        if (DISABLE_HW) {
            $ports = $this->read_fake_in_ports();
            $s = $ports[$port];
            $this->log->info("state %s -> %d\n",
                             port_str($pname, $this->name, 'in', $port), $s);
            return $s;
        }

        if ($this->name == 'usio1') {
            $s = usio()->input_get_state($port);
            if ($s >= 0) {
                $this->log->info("state %s -> %d\n",
                                 port_str($pname, $this->name, 'in', $port), $s);
                return $s;
            }
            $reason = '';
        } else {
            $ret = $this->send_cmd("input_get", ['port' => $port]);
            if ($ret['status'] == "ok") {
                $s = $ret['state'];
                $this->log->info("state %s -> %d\n",
                                 port_str($pname, $this->name, 'in', $port), $s);
                return $s;
            }
            $reason = $ret['reason'];
        }

        $err = sprintf("Can't get state %s for %s over HTTP. " .
                       "HTTP server return error: '%s'\n",
                        port_str($pname, $this->name, 'in', $port),
                        $this->ip_addr, $reason);

        $this->log->err($err);
        return $err;
    }

    private function read_fake_in_ports()
    {
        $io_name = $this->name;
        $ports = [];
        for ($i = 1; $i <= count(conf_io()[$io_name]['in']); $i++)
            $ports[$i] = 0;

        @$str = file_get_contents(sprintf('fake_%s_in.txt', $io_name));
        if (!$str)
            return $ports;

        $rows = string_to_array($str, "\n");
        for ($i = 1; $i <= count(conf_io()[$io_name]['in']); $i++)
            @$ports[$i] = $rows[$i - 1];

        return $ports;
    }

    private function read_fake_out_ports()
    {
        $io_name = $this->name;
        $ports = [];
        for ($i = 1; $i <= count(conf_io()[$io_name]['out']); $i++)
            $ports[$i] = 0;

        @$str = file_get_contents(sprintf('fake_%s_out.txt', $io_name));
        if (!$str)
            return $ports;

        $rows = string_to_array($str, "\n");
        for ($i = 1; $i <= count(conf_io()[$io_name]['out']); $i++)
            @$ports[$i] = $rows[$i - 1];

        return $ports;
    }

    private function write_fake_out_ports($ports)
    {
        $io_name = $this->name;
        foreach ($ports as $num => $value)
            $ports[$num] = $value ? '1' : '0';

        $str = array_to_string($ports, "\n");
        file_put_contents(sprintf('fake_%s_out.txt', $io_name), $str);
    }
}

class Board_io_in {
    function __construct($bord_io, $port)
    {
        $this->bord_io = $bord_io;
        $this->port = $port;
    }

    public function state()
    {
        return $this->bord_io->input_get_state($this->port);
    }
}


class Board_io_out {
    function __construct($bord_io, $port, $locked = false)
    {
        $this->bord_io = $bord_io;
        $this->port = $port;
        $this->locked = $locked;
    }

    function check_for_lock()
    {
        global $log;
        if (!$this->locked)
            return false;
        $pname = port_name_by_addr($this->bord_io->name,
                                   'out', $this->port);
        $info = port_info($pname);
        $log->warn('%s is locked', $info['str']);
        return true;
    }

    function up()
    {
        if ($this->check_for_lock())
            return 'port is locked';
        return $this->bord_io->relay_set_state($this->port, 1);
    }

    function down()
    {
        if ($this->check_for_lock())
            return 'port is locked';
        return $this->bord_io->relay_set_state($this->port, 0);
    }

    function state()
    {
        return $this->bord_io->relay_get_state($this->port);
    }
}

function port_str($pname, $io_name, $mode, $pn) {
    return sprintf("(%s/%s.%s.%s)", $pname, $io_name, $mode, $pn);
}

function port_info($pname)
{
    $info = NULL;
    $conf_io = conf_io();
    foreach ($conf_io as $io_name => $io_info) {
        foreach ($io_info['in'] as $num => $port_name)
            if ($port_name == $pname) {
                $info = ['io_name' => $io_name,
                         'info' => $io_info,
                         'mode' => 'in',
                         'pn' => $num];
                break;
            }

        foreach ($io_info['out'] as $num => $port_name)
            if ($port_name == $pname) {
                $info = ['io_name' => $io_name,
                         'info' => $io_info,
                         'mode' => 'out',
                         'pn' => $num];
                break;
            }
    }

    if (!isset($info['info']))
        return NULL;

    $info['pname'] = $pname;
    $info['str'] = port_str($info['pname'], $info['io_name'],
                            $info['mode'], $info['pn']);
    return $info;
}

function port_name_by_addr($io_name, $mode, $port_num)
{
    $conf = conf_io();
    if (!isset($conf[$io_name]))
        return '';

    if (!isset($conf[$io_name][$mode]))
        return '';

    if (!isset($conf[$io_name][$mode][$port_num]))
        return '';

    return conf_io()[$io_name][$mode][$port_num];
}


function board_io($name, $ip_addr, $tcp_port)
{
    static $board_io_list = [];

    if (!isset(conf_io()[$name]))
        return NULL; //TODO add logs

    if (isset($board_io_list[$name]))
        return $board_io_list[$name];

    $board_io = new Board_io($name, $ip_addr, $tcp_port);
    $board_io_list[$name] = $board_io;
    return $board_io;
}

function iop($pname)
{
    $info = port_info($pname);
    if (!$info)
        return NULL; // TODO add logs

    $board_io = board_io($info['io_name'],
                         $info['info']['ip_addr'],
                         $info['info']['tcp_port']);

    if ($info['mode'] == 'in')
        return new Board_io_in($board_io, $info['pn']);

    if ($info['mode'] == 'out')
        return new Board_io_out($board_io, $info['pn'],
                                port_is_locked($pname));

    return NULL; // TODO add logs
}

function io_handlers_by_event($pname, $state)
{
    $list = [];
    foreach (io_handlers() as $handler)
        foreach ($handler->trigger_ports() as $trig_pname => $trig_state) {
            if ($trig_pname == $pname) {
                if ($trig_state > 1) {
                    $list[] = $handler;
                    continue;
                }
                if ($trig_state == $state) {
                    $list[] = $handler;
                    continue;
                }
            }
        }

    if (!count($list))
        return NULL;
    return $list;
}

function port_is_locked($pname)
{
    if (!count(settings_io()['locked_io']))
        return false;

    foreach (settings_io()['locked_io'] as $p)
        if ($p == $pname)
            return true;
    return false;
}

function trig_io_event($pname, $state)
{
    global $log;
    $info = port_info($pname);
    if (port_is_locked($pname)) {
        $log->warn('%s is locked', $info['str']);
        return 0;
    }

    db()->query('delete from io_events where ' .
                'created < (now() - interval 3 month)');

    db()->insert('io_events', ['port_name' => $pname,
                               'mode' => 'in',
                               'io_name' => $info['io_name'],
                               'port' => $info['pn'],
                               'state' => $state]);

    $handlers = io_handlers_by_event($pname, $state);
    if (!$handlers)
        return 0;

    $list = [];
    foreach ($handlers as $handler) {
        pnotice("triggering %s\n", $handler->name());
        $handler->event_handler($pname, $state);
        $list[] = $handler;
    }
    if (!count($list))
        return 0;
    return $list;
}


function trig_io_board_for_curr_state($io_name)
{
    foreach (conf_io()[$io_name]['in'] as $port_num => $pname) {
        if (!$pname)
            continue;
        $state = iop($pname)->state();
        trig_io_event($pname, $state);
    }
}

function refresh_out_ports()
{
    foreach(conf_io() as $io_name => $info) {
        foreach ($info['out'] as $port_num => $pname) {
            if (!$pname)
                continue;
            $info = port_info($pname);
            $row = db()->query(sprintf(
                               'SELECT state from io_events ' .
                               'WHERE mode = "out" AND ' .
                                   'port_name = "%s" AND ' .
                                   'port = %d ' .
                               'ORDER BY id DESC LIMIT 1',
                               $info['io_name'], $info['pn']));
            if (!is_array($row) || !isset($row['state']))
                continue;

            if ($row['state'])
                iop($pname)->up();
            else
                iop($pname)->down();
        }
    }
}


function io_states()
{
    $query = 'SELECT io_events.io_name, ' .
                    'io_events.port, ' .
                    'io_events.state ' .
             'FROM io_events ' .
             'INNER JOIN ' .
                '( SELECT io_name, port, max(id) as last_id ' .
                 'FROM io_events ' .
                 'GROUP BY io_name, port ) as b '.
             'ON io_events.port = b.port AND ' .
                'io_events.io_name = b.io_name AND ' .
                'io_events.id = b.last_id ' .
             'ORDER BY io_events.io_name, io_events.port';

    $rows = db()->query_list($query);
    if (!is_array($rows) || !count($rows))
        return [];

    return $rows;
}


class Boards_io_cron_events implements Cron_events {
    function name() {
        return "board_io";
    }

    function interval() {
        return "min";
    }

    function do()
    {
        $temperatures = [];
        foreach(conf_io() as $io_name => $io_data) {
            if ($io_name == 'usio1')
                continue;

            @$content = file_get_contents(sprintf('http://%s:%d/stat',
                                         $io_data['ip_addr'], $io_data['tcp_port']));
            if ($content === FALSE) {
                tn()->send_to_admin("Сбой связи с модулем %s", $io_name);
                trig_io_board_for_curr_state($io_name);
                continue;
            }

            $response = json_decode($content, true);
            if ($response === NULL) {
                tn()->send_to_admin("Модуль ввода вывода %s вернул не корректный ответ на запрос: %s",
                                        $io_name, $content);
                continue;
            }

            if ($response['status'] != 'ok') {
                tn()->send_to_admin("При опросе модуля ввода-вывода %s, он вернул ошибку: %s",
                                        $io_name, $response['error_msg']);
                continue;
            }

            if ($response['uptime'] == '0 min' || $response['uptime'] == '1 min')
                tn()->send_to_admin("Модуль ввода-вывода %s недавно перезагрузился", $io_name);

            if (isset($response['trigger_log']) && count($response['trigger_log'])) {
                foreach ($response['trigger_log'] as $time => $msg) {
                    tn()->send_to_admin("Модуль ввода-вывода %s сообщил, " .
                                        "что не смог вовремя передать событие %s. " .
                                        "Которое произошло %s",
                                        $io_name, $msg, date("m.d.Y H:i:s", $time));
                }
            }
        }

        file_put_contents(CURRENT_TEMPERATURES_FILE, json_encode($temperatures));
    }
}


class Http_io_handler implements Http_handler {
    function name() {
        return "board_io";
    }

    function requests() {
        return ['/ioserver' => ['method' => 'GET',
                               'required_args' => ['io',
                                                   'port',
                                                   'state'],
                               'handler' => 'trig_io',
                                                   ]
        ];
    }

    function __construct() {
        $this->log = new Plog('sr90:Http_io_handler');
    }

    function trig_io($args, $from, $request)
    {
        $io_name = strtolower(trim($args['io']));
        $port_num = strtolower(trim($args['port']));
        $state = strtolower(trim($args['state']));

        $pname = port_name_by_addr($io_name, 'in', $port_num);

        if (!$pname) {
            $err = sprintf("port %s is not registred\n",
                           port_str($pname, $io_name, 'in', $port_num));
            $this->log->err($err);
            return json_encode(['status' => 'error',
                                'reason' => $err]);
        }

        if ($state < 0 || $state > 1) {
            $err = sprintf("Incorrect port state %d. Port state must be 0 or 1\n", $state);
            $this->log->err($err);
            return json_encode(['status' => 'error',
                                'reason' => $err]);
        }

        trig_io_event($pname, $state);
        return json_encode(['status' => 'ok']);
    }
}


