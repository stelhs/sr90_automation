<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';

require_once 'common_lib.php';
require_once 'config.php';
require_once 'settings.php';

define("FAKE_IN_FILE", "fake_in_ports");
define("FAKE_OUT_FILE", "fake_out_ports");
define("CURRENT_TEMPERATURES_FILE", "/tmp/current_temperatures");


class Io {
    function __construct()
    {
        $this->log = new Plog('sr90:io');
        if (DISABLE_HW) {
            if (!file_exists(FAKE_IN_FILE)) {
                $str = '';
                foreach (conf_io() as $io_name => $io_info)
                    foreach ($io_info['in'] as $pn => $pname) {
                        if ($pname)
                            $str .= sprintf("0: %s\n", $pname);
                    }
                file_put_contents(FAKE_IN_FILE, $str);
            }

            if (!file_exists(FAKE_OUT_FILE)) {
                $str = '';
                foreach (conf_io() as $io_name => $io_info)
                    foreach ($io_info['out'] as $pn => $pname) {
                        if ($pname)
                            $str .= sprintf("0: %s\n", $pname);
                    }
                file_put_contents(FAKE_OUT_FILE, $str);
            }
        }

        $this->boards = [];
        foreach (conf_io() as $io_name => $io_info) {
            switch($io_info['type']) {
            case 'usio':
                $this->boards[$io_name] = new Usio($io_name);
                break;
            case 'sbio':
                $this->boards[$io_name] = new Sbio($io_name);
                break;
            case 'mbio':
                $this->boards[$io_name] = new Mbio($io_name);
                break;
            }
        }
    }

    function board($name) {
        return $this->boards[$name];
    }

    function port($pname)
    {
        foreach (conf_io() as $io_name => $io_info) {
            $board = $this->boards[$io_name];
            $port = $board->port($pname);
            if ($port)
                return $port;
        }
        return NULL;
    }

    function ports($mode = NULL) {
        $list = [];
        foreach ($this->boards as $board)
            foreach ($board->ports($mode) as $port)
                $list[] = $port;
        return $list;
    }

    function locked_ports()
    {
        $list = [];
        foreach ($this->ports() as $port) {
            if ($port->is_locked())
                $list[] = $port;
        }
        return $list;
    }

    function port_by_addr($io_name, $mode, $pn)
    {
        $conf = conf_io();
        if (!isset($conf[$io_name]))
            return NULL;

        if (!isset($conf[$io_name][$mode]))
            return NULL;

        if (!isset($conf[$io_name][$mode][$pn]))
            return NULL;

        return $this->board($io_name)->ports($mode)[$pn];
    }


    function trig_event($port, $state)
    {
        if ($port->is_locked()) {
            $this->log->warn('%s is locked', $port->str());
            return 0;
        }

        $state = (int)$state;
        if ($state != 0 and $state != 1) {
            $this->log->err('port state %s is not correct. state = %s',
                            $port->str(), $state);
            return 0;
        }

        db()->query('delete from io_events where ' .
                    'created < (now() - interval 3 month)');

        $row_id = db()->insert('io_events',
                               ['port_name' => $port->name(),
                                'mode' => 'in',
                                'io_name' => $port->board()->name(),
                                'port' => $port->pn(),
                                'state' => $state]);

        if ($row_id < 0) {
            $this->log->err('Can`t insert into io_events table. %s :%s',
                             $port->str(), $state);
            return 0;
        }

        $list = [];
        foreach (io_handlers() as $handler) {
            foreach ($handler->trigger_ports() as $f => $plist) {
                foreach ($plist as $pname => $trig_val) {
                    if ($pname != $port->name())
                        continue;

                    if ($trig_val > 1) {
                        $handler->$f($port, $state);
                        $list[] = $handler;
                        continue;
                    }
                    if ($trig_val == $state) {
                        $handler->$f($port, $state);
                        $list[] = $handler;
                        continue;
                    }
                }
            }
        }

        if (!count($list))
            return 0;

        return $list;
    }

    function stored_states()
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

    function refresh_out_ports()
    {
        foreach(conf_io() as $io_name => $info) {
            foreach ($info['out'] as $port_num => $pname) {
                if (!$pname)
                    continue;
                $port = io()->port($pname);
                $row = db()->query(sprintf(
                                   'SELECT state from io_events ' .
                                   'WHERE mode = "out" AND ' .
                                       'port_name = "%s" AND ' .
                                       'port = %d ' .
                                   'ORDER BY id DESC LIMIT 1',
                                   $port->board()->name(), $port->pn()));
                if (!is_array($row) || !isset($row['state']))
                    continue;

                if ($row['state'])
                    $port->up();
                else
                    $port->down();
            }
        }
    }

    function termosensors()
    {
        if (!file_exists(CURRENT_TEMPERATURES_FILE))
            return [];

        $content = file_get_contents(CURRENT_TEMPERATURES_FILE);
        if (!$content)
            return [];

        @$rows = json_decode($content, true);
        if (!$rows || !is_array($rows))
            return [];

        $list = [];
        foreach ($rows as $row) {
            if (!isset(conf_termo_sensors()[$row['sensor_name']]))
                continue;
            $list[] = ['name' => conf_termo_sensors()[$row['sensor_name']],
                       'value' => $row['temperature'],
                       'sensor_name' => $row['sensor_name'],
            ];
        }
        return $list;
    }


    function stat_text()
    {
        $tg = "Заблокированные порты:\n";
        $sms = 'locked_ports:';
        $sep = '';

        $ports = $this->locked_ports();
        if ($ports) {
            foreach ($ports as $port) {
                $tg .= sprintf("    %s\n", $port->name());
                $sms .= sprintf("%s%s", $sep, $port->name());
                $sep = ',';
            }
        }

        $tlist = $this->termosensors();
        if ($tlist)
            foreach($tlist as $sensor)
                $tg .= sprintf("Температура %s: %.01f градусов\n",
                               $sensor['name'], $sensor['value']);

        return [$tg, $sms];
    }

    private function pid_sequence_file_by_pname($pname) {
        return sprintf('/tmp/io_sequence_%s_pid', $pname);
    }

    private function pid_sequence_task($tname) {
        $fname = $this->pid_sequence_file_by_pname($tname);
        if (!file_exists($fname))
            return 0;
        $pid = file_get_contents($fname);
        return $pid;
    }

    function sequnce_start($pname, $sequence)
    {
        $pid = $this->pid_sequence_task($pname);
        if ($pid)
            return;

        $seq_str = array_to_string($sequence, ' ');
        $cmd = sprintf("./io_sequencer.sh %s %s \"%s\"",
                       $this->pid_sequence_file_by_pname($pname),
                       $pname, $seq_str);
        dump($cmd);
        run_cmd($cmd, true, '', false);
        usleep(500 * 1000);
        $pid = $this->pid_sequence_task($pname);
        pnotice("IO sequence task %s/%d started\n", $pname, $pid);
    }

    function sequnce_stop($pname)
    {
        $pid = $this->pid_sequence_task($pname);
        if (!$pid)
            return;
        $pid_file = $this->pid_sequence_file_by_pname($pname);
        stop_daemon($pid_file);
    }
}


class Board_io {
    function __construct($io_name)
    {
        $this->ip_addr = conf_io()[$io_name]['ip_addr'];
        $this->tcp_port = conf_io()[$io_name]['tcp_port'];
        $this->io_name = $io_name;
        $this->log = new Plog(sprintf('sr90:%s', $io_name));

        $this->ports = [];
        foreach (conf_io()[$io_name]['in'] as $pn => $pinfo) {
            $pname = is_array($pinfo) ? $pinfo['name'] : "";
            $this->ports[] = new Io_in_port($this, $pn, $pname);
        }

        foreach (conf_io()[$io_name]['out'] as $pn => $pname)
            $this->ports[] = new Io_out_port($this, $pn, $pname);
    }

    function name() {
        return $this->io_name;
    }

    function blink($port, $d1, $d2 = 0, $cnt = 0) {
        return;
    }

    function port($pname) {
        foreach ($this->ports as $port) {
            if ($port->name() == $pname)
                return $port;
        }
    }

    function ports($mode = NULL) {
        $list = [];
        foreach ($this->ports as $port) {
            if ($mode) {
                if ($port->mode() == $mode)
                    $list[$port->pn()] = $port;
                continue;
            }

            $list[] = $port;
        }
        return $list;
    }

    function fake_relay_set($port, $state)
    {
        $ports = $this->parse_fake_file(FAKE_OUT_FILE);
        $ports[$port->name()] = $state;
        $str = '';
        foreach (conf_io() as $io_name => $io_info)
            foreach ($io_info['out'] as $pn => $pname) {
                if ($pname)
                    $str .= sprintf("%d: %s\n", $ports[$pname], $pname);
            }
        file_put_contents(FAKE_OUT_FILE, $str);
        return [0, 'ok'];
    }

    function fake_relay_state($port)
    {
        $ports = $this->parse_fake_file(FAKE_OUT_FILE);
        $s = $ports[$port->name()];
        $this->log->info("state %s -> %d\n", $port->str(), $s);
        return [$s, 'ok'];
    }

    function fake_input_state($port)
    {
        $ports = $this->parse_fake_file(FAKE_IN_FILE);
        if (!$port->name()) {
            $this->log->info("state %s -> %d\n", $port->str(), 0);
            return [0, 'ok'];
        }
        $s = $ports[$port->name()];
        $this->log->info("state %s -> %d\n", $port->str(), $s);
        return [$s, 'ok'];
    }

    private function parse_fake_file($file)
    {
        $list = [];
        $c = file_get_contents($file);
        $rows = string_to_rows($c);
        foreach ($rows as $row) {
            $words = string_to_words($row, ':');
            $list[$words[1]] = $words[0];
        }
        return $list;
    }

    function trig_all_ports()
    {
        foreach (conf_io() as $io_name => $info)
            foreach ($info['in'] as $port_num => $pinfo) {
                if (!is_array($pinfo))
                    continue;
                $pname = $pinfo['name'];
                if (!$pname)
                    continue;
                $state = iop($pname)->state()[0];
                io()->trig_event(io()->port($pname), $state);
            }
    }
}


class Sbio extends Board_io {
    function __construct($io_name) {
        parent::__construct($io_name);
        $this->type = "sbio";
    }

    function send_cmd($cmd, $args)
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
        $content = file_get_contents_safe($http_request);
        if (!$content) {
            $this->log->err("Null bytes received from addr: %s", $http_request);
            return ['status' => 'error',
            		'reason' => 'connection erro  r'];
        }

        $ret_data = json_decode($content, true);
        if (!$ret_data) {
            $this->log->err("Can't decode JSON from addr: %s", $http_request);
            return ['status' => 'error',
            		'reason' => 'json decode error'];
        }
        return $ret_data;
    }

    function relay_set($port, $state)
    {
        if (DISABLE_HW)
            return $this->fake_relay_set($port, $state);

        $ret = $this->send_cmd("relay_set", ['port' => $port->pn(),
                                             'state' => $state]);
        if ($ret['status'] == "ok")
            return [0, 'ok'];

        $err = sprintf("Can't set %s: %d over HTTP. " .
                        "HTTP server return error: '%s'\n",
                        $port->str(), $state, $ret['reason']);
        $this->log->err($err);
        return [-1, $err];
    }

    function relay_state($port)
    {
        if (DISABLE_HW)
            return $this->fake_relay_state($port);

        $ret = $this->send_cmd("relay_get", ['port' => $port->pn()]);
        if ($ret['status'] == "ok") {
            return [$ret['state'], 'ok'];
        }

        $err = sprintf("Can't get %s over HTTP. " .
                        "HTTP server return error: '%s'\n",
                        $port->str(), $ret['reason']);
        $this->log->err($err);
        return [-1, $err];
    }

    function input_state($port)
    {
        if (DISABLE_HW)
            return $this->fake_input_state($port);

        $ret = $this->send_cmd("input_get", ['port' => $port->pn()]);
        if ($ret['status'] == "ok")
            return [$ret['state'], 'ok'];

        $err = sprintf("Can't get state %s over HTTP. " .
                       "HTTP server return error: '%s'\n",
                        $port->str(), $ret['reason']);
        $this->log->err($err);
        return [-1, $err];
    }

    function reboot()
    {
        $request = sprintf("http://%s:%d/reboot",
                           $this->ip_addr,
                           $this->tcp_port);
        $content = file_get_contents_safe($request);
        if (!$content) {
            $this->log->err("can't HTTP request %s\n", $request);
            return ['status' => 'error',
                    'error_msg' => sprintf('Can`t response from %s', $this->io_name)];
        }

        $ret_data = json_decode($content, true);
        if (!$ret_data) {
            $log->err("can't decode JSON: %s\n", $content);
            return ['status' => 'error',
                    'error_msg' => sprintf('Can`t decoded response: %s', $content)];
        }

        if ($ret_data['status'] != 'ok') {
            $log->err("error response %s\n", $content);
            return -1;
        }
        return 0;
    }
}

class Mbio extends Sbio {
    function __construct($io_name) {
        parent::__construct($io_name);
        $this->type = "mbio";
    }

    function blink($port, $d1, $d2 = 0, $cnt = 0) {
        if (DISABLE_HW)
            return $this->fake_relay_set($port, 'blink');

        $ret = $this->send_cmd("relay_set", ['port' => $port->pn(),
                                             'state' => 'blink',
                                             'd1' => $d1,
                                             'd2' => $d2,
                                             'cnt' => $cnt]);
        if ($ret['status'] == "ok")
            return [0, 'ok'];

        $err = sprintf("Can't set to blink (%d, %d, %d) %s: %d over HTTP. " .
                        "HTTP server return error: '%s'\n",
                        $d1, $d2, $cnt,
                        $port->str(), $state, $ret['reason']);
        $this->log->err($err);
        return [-1, $err];
    }
}


class Usio extends Board_io{
    function __construct($io_name) {
        parent::__construct($io_name);
        $this->type = "usio";
    }

    private function send_cmd($cmd)
    {
        $result = "";
        $fd = stream_socket_client(sprintf("unix://%s",
                                           conf_local_io()['socket_file']),
                                   $errno, $errstr, 30);
        if (!$fd) {
            $this->log->err('cant stream_socket_client: %s\n', $fd);
            return -EBUSY;
        }

        fwrite($fd, $cmd);
        while (!feof($fd))
            $result .= fgets($fd, 1024);
        fclose($fd);

        return trim($result);
    }

    public function relay_set($port, $state)
    {
        if (DISABLE_HW)
            return $this->fake_relay_set($port, $state);

        $ret = $this->send_cmd(sprintf("relay_set %d %d\n",
                                        $port->pn(), $state));
        if ($ret == "ok")
            return [0, 'ok'];

        $err = sprintf("Can't set %s: %d over USIO driver. " .
                        "error: '%s'\n",
                        $port->str(), $state, $ret);
        $this->log->err($err);
        return [-1, $err];
    }

    public function relay_state($port)
    {
        if (DISABLE_HW)
            return $this->fake_relay_state($port);

        $ret = $this->send_cmd(sprintf("relay_get %d\n", $port->pn()));
        if ($ret == "0" || $ret == "1") {
            return [$ret, 'ok'];
        }

        $err = sprintf("Can't get %s over USIO driver. " .
                        "error: '%s'\n",
                        $port->str(), $ret);
        $this->log->err($err);
        return [-1, $err];
    }

    public function input_state($port)
    {
        if (DISABLE_HW)
            return $this->fake_input_state($port);

        $ret = $this->send_cmd(sprintf("input_get %d\n", $port->pn()));
        if ($ret == "0" || $ret == "1") {
            return [$ret, 'ok'];
        }

        $err = sprintf("Can't get state %s over USIO driver. " .
                       "error: '%s'\n",
                        $port->str(), $ret);
        $this->log->err($err);
        return [-1, $err];
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


class Io_port {
    function __construct($board, $mode, $pn, $pname) {
        $this->pname = $pname;
        $this->board = $board;
        $this->mode = $mode;
        $this->pn = $pn;
        $this->log = new Plog(sprintf('sr90:Io_port_%s', $pname));
        $this->hide_logs = false;
    }

    function is_locked()
    {
        if (!count(settings_io()['locked_io']))
            return false;

        foreach (settings_io()['locked_io'] as $p) {
            if ($p == $this->pname)
                return true;
        }
        return false;
    }

    function str() {
        return sprintf("(%s/%s.%s.%s)",
                       $this->pname, $this->board->name(),
                       $this->mode, $this->pn);
    }

    function name() {
        return $this->pname;
    }

    function mode() {
        return $this->mode;
    }

    function pn() {
        return $this->pn;
    }

    function board() {
        return $this->board;
    }

    function disable_logs() {
        $this->hide_logs = true;
    }

    function enable_logs() {
        $this->hide_logs = false;
    }
}

class Io_in_port extends Io_port {
    function __construct($board, $pn, $pname) {
        parent::__construct($board, 'in', $pn, $pname);
    }

    function state() {
        $s = $this->board->input_state($this);
        if (!$this->hide_logs)
            $this->log->info("state %s -> %d\n", $this->str(), $s[0]);
        return $s;
    }
}


class Io_out_port extends Io_port {
    function __construct($board, $pn, $pname) {
        parent::__construct($board, 'out', $pn, $pname);
    }

    function up() {
        return $this->set_val(1);
    }

    function down() {
        return $this->set_val(0);
    }

    function set_val($val) {
        if (!$this->hide_logs)
            $this->log->info("set %s -> %d\n", $this->str(), $val);
        $row_id = db()->insert('io_events',
                                ['mode' => 'out',
                                 'port_name' => $this->name(),
                                 'io_name' => $this->board->name(),
                                 'port' => $this->pn(),
                                 'state' => $val]);
        if (!$row_id)
            $this->log->err("Can't insert into io_events");
        return $this->board->relay_set($this, $val);
    }

    function blink($d1, $d2 = 0, $cnt = 0) {
        return $this->board->blink($this, $d1, $d2, $cnt);
    }

    function state() {
        $s = $this->board->relay_state($this);
        if (!$this->hide_logs)
            $this->log->info("state %s -> %d\n", $this->str(), $s[0]);
        return $s;
    }
}




function io()
{
    static $io = NULL;
    if (!$io)
        $io = new Io;

    return $io;
}

function iop($pname) {
    return io()->port($pname);
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
        if (DISABLE_HW)
            return;

        $temperatures = [];
        foreach(conf_io() as $io_name => $io_data) {
            if ($io_name == 'usio1')
                continue;

            @$content = file_get_contents_safe(sprintf('http://%s:%d/stat',
                                         $io_data['ip_addr'], $io_data['tcp_port']));
            if ($content === FALSE) {
                tn()->send_to_admin("Сбой связи с модулем %s", $io_name);
                io()->board($io_name)->trig_all_ports();
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
                               ],
                '/ioconfig' => ['method' => 'GET',
                                'required_args' => ['io'],
                                'handler' => 'io_config',
                               ],
        ];
    }

    function __construct() {
        $this->log = new Plog('sr90:Http_io_handler');
    }

    function trig_io($args, $from, $request)
    {
        $io_name = strtolower(trim($args['io']));
        $pn = strtolower(trim($args['port']));
        $state = strtolower(trim($args['state']));

        $port = io()->port_by_addr($io_name, 'in', $pn);
        if (!$port) {
            $err = sprintf("port %s:%s is not registred\n", $io_name, $pn);
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

        io()->trig_event($port, $state);
        return json_encode(['status' => 'ok']);
    }

    function io_config($args, $from, $request)
    {
        $io_name = strtolower(trim($args['io']));
        if (!isset(conf_io()[$io_name])) {
            $err = sprintf("IO board %s does not exist\n", $io_name);
            $this->log->err($err);
            return json_encode(['status' => 'error',
                                'reason' => $err]);
        }

        $in_list = conf_io()[$io_name]['in'];
        $out_list = conf_io()[$io_name]['out'];
        return json_encode(['status' => 'ok',
                            'ports' => ['in' => $in_list,
                                        'out' => $out_list]]);

    }
}


