<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';

require_once 'common_lib.php';
require_once 'config.php';
require_once 'settings.php';
require_once 'termosensors_api.php';

define("FAKE_IN_FILE", "fake_in_ports");
define("FAKE_OUT_FILE", "fake_out_ports");


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


    function trig_event($port, $state, $force = false)
    {
        if (!$force and $port->is_blocked()) {
            $this->log->warn('%s is blocked', $port->str());
            return 0;
        }

        $state = (int)$state;
        if ($state != 0 and $state != 1) {
            $this->log->err('port state %s is not correct. state = %s',
                            $port->str(), $state);
            return 0;
        }

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

        $tlist = termosensors()->list();
        if ($tlist)
            foreach($tlist as $ts)
                $tg .= sprintf("Температура %s: %.01f градусов\n",
                               $ts->description(), $ts->t());

        return [$tg, $sms];
    }

    function ui_update_blocked_ports()
    {
        $rows = db()->query_list('select * from blocked_io_ports');
        if (!is_array($rows))
            return;
        ui()->notify('io', 'boardsBlokedPortsList', $rows);
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

    function temperatures() {
        $http_request = sprintf("http://%s:%d/stat",
                                $this->ip_addr,
                                $this->tcp_port);
        $content = file_get_contents_safe($http_request);
        if (!$content) {
            $this->log->err("Null bytes received from addr: %s", $http_request);
            return [-1, 'connection error'];
        }

        $ret_data = json_decode($content, true);
        if (!$ret_data) {
            $log->err("can't decode JSON: %s\n", $content);
            return [-1, sprintf('Can`t decoded response: %s', $content)];
        }

        if (!isset($ret_data['termo_sensors'])) {
            $msg = sprintf("Can't getting termosensor %s info: %s\n",
                            $addr, $ret_data['reason']);
            $log->err($msg);
            return [-1, $msg];
        }
        $list = [];
        foreach ($ret_data['termo_sensors'] as $row) {
            $list[$row['name']] = $row['temperature'];
        }
        return [0, $list];
    }

    function temperature($addr) {
        $http_request = sprintf("http://%s:%d/stat",
                                $this->ip_addr,
                                $this->tcp_port);
        $content = file_get_contents_safe($http_request);
        if (!$content) {
            $this->log->err("Null bytes received from addr: %s", $http_request);
            return [-1, 'connection error'];
        }

        $ret_data = json_decode($content, true);
        if (!$ret_data) {
            $log->err("can't decode JSON: %s\n", $content);
            return [-1, sprintf('Can`t decoded response: %s', $content)];
        }

        if (!isset($ret_data['termo_sensors'])) {
            $msg = sprintf("Can't getting termosensor %s info: %s\n",
                            $addr, $ret_data['reason']);
            $log->err($msg);
            return [-1, $msg];
        }

        foreach ($ret_data['termo_sensors'] as $row)
            if ($row['name'] == $addr)
                return [0, $row['temperature']];

        return [-1, sprintf("can't found termosensor %s", $addr)];
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

    function battery_info() {
        $http_request = sprintf("http://%s:%d/battery",
                                $this->ip_addr,
                                $this->tcp_port);
        $content = file_get_contents_safe($http_request);
        if (!$content) {
            $this->log->err("Null bytes received from addr: %s", $http_request);
            return [-1, 'connection error'];
        }

        $ret_data = json_decode($content, true);
        if (!$ret_data) {
            $log->err("can't decode JSON: %s\n", $content);
            return [-1, sprintf('Can`t decoded response: %s', $content)];
        }
        return [0, $ret_data];
    }

    function temperatures() {
        $http_request = sprintf("http://%s:%d/termosensors",
                                $this->ip_addr,
                                $this->tcp_port);
        $content = file_get_contents_safe($http_request);
        if (!$content) {
            $this->log->err("Null bytes received from addr: %s", $http_request);
            return [-1, 'connection error'];
        }

        $ret_data = json_decode($content, true);
        if (!$ret_data) {
            $log->err("can't decode JSON: %s\n", $content);
            return [-1, sprintf('Can`t decoded response: %s', $content)];
        }

        if ($ret_data['status'] != 'ok') {
            $msg = sprintf("Can't getting termosensor %s info: %s\n",
                            $addr, $ret_data['reason']);
            $log->err($msg);
            return [-1, $msg];
        }

        return [0, $ret_data['list']];
    }

    function temperature($addr) {
        $http_request = sprintf("http://%s:%d/termosensors?addr=%s",
                                $this->ip_addr,
                                $this->tcp_port,
                                $addr);
        $content = file_get_contents_safe($http_request);
        if (!$content) {
            $this->log->err("Null bytes received from addr: %s", $http_request);
            return [-1, 'connection error'];
        }

        $ret_data = json_decode($content, true);
        if (!$ret_data) {
            $log->err("can't decode JSON: %s\n", $content);
            return [-1, sprintf('Can`t decoded response: %s', $content)];
        }

        if ($ret_data['status'] != 'ok') {
            $msg = sprintf("Can't getting termosensor %s info: %s\n",
                            $addr, $ret_data['reason']);
            $log->err($msg);
            return [-1, $msg];
        }

        return [0, $ret_data['t']];
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

    function is_blocked()
    {
        $row = db()->query("select state from blocked_io_ports " .
                           "where port_name = '%s'", $this->pname);
        if (!is_array($row) || !isset($row['state']))
            return false;

        return true;
    }

    function lock()
    {
        if ($this->is_blocked())
            return;
        db()->insert('blocked_io_ports',
                     ['port_name' => $this->name(),
                      'type' => $this->mode(),
                      'state' => 0]);
        io()->ui_update_blocked_ports();
    }

    function unlock() {
        db()->query('delete from blocked_io_ports where port_name = "%s"', $this->name());
        io()->ui_update_blocked_ports();
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

    function blocked_state() {
        if (!$this->is_blocked())
            return NULL;
        $row = db()->query('select state from blocked_io_ports where port_name = "%s"',
                           $this->name());
        if (!is_array($row))
            return NULL;

        return $row['state'];
    }

    function state($force = false) {
        if (!$force and $this->is_blocked()) {
            $s = $this->blocked_state();
            if (!$this->hide_logs)
                $this->log->info("blocked state %s -> %d\n", $this->str(), $s);
            return [ $s,  'ok'];
        }

        $s = $this->board->input_state($this);
        if (!$this->hide_logs)
            $this->log->info("state %s -> %d\n", $this->str(), $s[0]);
        return $s;
    }

    function set_blocked_state($state)
    {
        if (!$this->is_blocked())
            return;

        db()->query('update blocked_io_ports set state = %d where port_name = "%s"',
                    $state, $this->name());
        io()->ui_update_blocked_ports();
        io()->trig_event($this, $state, true);
    }
}


class Io_out_port extends Io_port {
    function __construct($board, $pn, $pname) {
        parent::__construct($board, 'out', $pn, $pname);
    }

    function up($force = false) {
        if ($this->is_blocked() and !$force) {
            $this->log->info("port is blocked, set state to '1' was ignored");
            return [0, 'ok'];
        }
        return $this->set_val(1);
    }

    function down($force = false) {
        if ($this->is_blocked() and !$force) {
            $this->log->info("port is blocked, set state to '0' was ignored");
            return [0, 'ok'];
        }
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
        if (!$this->hide_logs)
            $this->log->info("blink %s: %d, %d, %d\n", $this->str(), $d1, $d2, $cnt);
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


class Boards_io_min_cron_events implements Cron_events {
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
                                        $io_name, $response['reason']);
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

#        file_put_contents(CURRENT_TEMPERATURES_FILE, json_encode($temperatures));
    }
}

class Boards_io_day_cron_events implements Cron_events {
    function name() {
        return "board_io";
    }

    function interval() {
        return "day";
    }

    function do()
    {
        db()->query('delete from io_events where ' .
                    'created < (now() - interval 3 month)');
    }
}


class Http_io_handler implements Http_handler {
    function name() {
        return "board_io";
    }

    function requests() {
        return ['/io/send_event' => ['method' => 'GET',
                                'required_args' => ['io',
                                                    'port',
                                                    'state'],
                                'handler' => 'trig_io'],

                '/io/config' => ['method' => 'GET',
                                'handler' => 'io_config'],

                '/io/blocked_ports' => ['method' => 'GET',
                                        'handler' => 'blocked_ports'],

                '/io/ui_update' => ['method' => 'GET',
                                                'handler' => 'ui_update'],

                '/io/termosensor_config' => ['method' => 'GET',
                                             'required_args' => ['io'],
                                             'handler' => 'termosensor_config'],

                '/io/port/lock' => ['method' => 'GET',
                                    'required_args' => ['port_name'],
                                    'handler' => 'lock_port'],

                '/io/port/unlock' => ['method' => 'GET',
                                      'required_args' => ['port_name'],
                                      'handler' => 'unlock_port'],

                '/io/port/set_blocked_state' => ['method' => 'GET',
                                                 'required_args' => ['port_name', 'state'],
                                                 'handler' => 'set_blocked_state'],

                '/io/port/blink' => ['method' => 'GET',
                                     'required_args' => ['port_name', 'd1', 'd2', 'number'],
                                     'handler' => 'port_blink'],

                '/io/port/toggle_state' => ['method' => 'GET',
                                            'required_args' => ['port_name'],
                                            'handler' => 'port_toggle_state'],
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
            $err = sprintf("port %s:%s is not registred", $io_name, $pn);
            $this->log->err($err);
            return json_encode(['status' => 'error',
                                'reason' => $err]);
        }

        if ($state < 0 || $state > 1) {
            $err = sprintf("Incorrect port state %d. Port state must be 0 or 1", $state);
            $this->log->err($err);
            return json_encode(['status' => 'error',
                                'reason' => $err]);
        }

        io()->trig_event($port, $state);
        return json_encode(['status' => 'ok']);
    }

    function io_config($args, $from, $request)
    {
        $io_name = NULL;
        if (isset($args['io']))
            $io_name = strtolower(trim($args['io']));

        if ($io_name) {
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

        $boards = [];
        foreach (conf_io() as $io_name => $info) {
            $in_list = conf_io()[$io_name]['in'];
            $out_list = conf_io()[$io_name]['out'];
            $boards[$io_name] = ['in' => $in_list,
                                 'out' => $out_list];
        }
        return json_encode(['status' => 'ok',
                            'boards' => $boards]);

    }

    function blocked_ports($args, $from, $request)
    {
        $rows = db()->query_list('select * from blocked_io_ports');
        if (!is_array($rows))
            return json_encode(['status' => 'error',
                                'reason' => 'Can`t get "blocked_io_ports" from MySQL']);

        return json_encode(['status' => 'ok',
                            'list' => $rows]);
    }

    function termosensor_config($args, $from, $request)
    {
        $io_name = strtolower(trim($args['io']));
        if (!isset(conf_io()[$io_name])) {
            $err = sprintf("IO board %s does not exist\n", $io_name);
            $this->log->err($err);
            return json_encode(['status' => 'error',
                                'reason' => $err]);
        }
        $sensors = termosensors()->list_by_board_name($io_name);
        $list = [];
        foreach ($sensors as $tsensor)
            $list[] = $tsensor->addr();
        return json_encode(['status' => 'ok',
                            'list' => $list]);
    }

    function lock_port($args, $from, $request)
    {
        $port_name = $args['port_name'];
        $port = io()->port($port_name);
        if (!$port) {
            $err = sprintf("lock_port() failed: port '%s' not found", $port_name);
            $this->log->err($err);
            return json_encode(['status' => 'error',
                                'reason' => $err]);
        }

        $port->lock();
        return json_encode(['status' => 'ok']);
    }

    function unlock_port($args, $from, $request)
    {
        $port_name = $args['port_name'];
        $port = io()->port($port_name);
        if (!$port) {
            $err = sprintf("lock_port() failed: port '%s' not found", $port_name);
            $this->log->err($err);
            return json_encode(['status' => 'error',
                                'reason' => $err]);
        }

        $port->unlock();
        return json_encode(['status' => 'ok']);
    }

    function set_blocked_state($args, $from, $request)
    {
        $port_name = $args['port_name'];
        $state = (int)$args['state'];
        $port = io()->port($port_name);
        if (!$port) {
            $err = sprintf("set_blocked_state() failed: port '%s' not found", $port_name);
            $this->log->err($err);
            return json_encode(['status' => 'error',
                                'reason' => $err]);
        }

        if ($port->mode() != 'in') {
            $err = sprintf("set_blocked_state() failed: port '%s' must be configured as input mode",
                            $port_name);
            $this->log->err($err);
            return json_encode(['status' => 'error',
                                'reason' => $err]);
        }

        if (!$port->is_blocked()) {
            $err = sprintf("set_blocked_state() failed: port '%s' must be marked as blocked",
                            $port_name);
            $this->log->err($err);
            return json_encode(['status' => 'error',
                                'reason' => $err]);
        }

        $port->set_blocked_state($state);
        return json_encode(['status' => 'ok']);
    }

    function port_blink($args, $from, $request)
    {
        $port_name = $args['port_name'];
        $d1 = $args['d1'];
        $d2 = $args['d2'];
        $number = $args['number'];

        $port = io()->port($port_name);
        if (!$port) {
            $err = sprintf("port_blink() failed: port '%s' not found", $port_name);
            $this->log->err($err);
            return json_encode(['status' => 'error',
                                'reason' => $err]);
        }

        $port->blink($d1, $d2, $number);
        return json_encode(['status' => 'ok']);
    }

    function port_toggle_state($args, $from, $request)
    {
        $port_name = $args['port_name'];
        $port = io()->port($port_name);
        if (!$port) {
            $err = sprintf("port_toggle_state() failed: port '%s' not found", $port_name);
            $this->log->err($err);
            return json_encode(['status' => 'error',
                                'reason' => $err]);
        }

        if ($port->mode() != 'out') {
            $err = sprintf("port_toggle_state() failed: port '%s' must be configured as 'out'", $port_name);
            $this->log->err($err);
            return json_encode(['status' => 'error',
                                'reason' => $err]);
        }

        $state = $port->state()[0];
        if ($state)
            $port->down(true);
        else
            $port->up(true);
        return json_encode(['status' => 'ok']);
    }

    function ui_update($args, $from, $request)
    {
        io()->ui_update_blocked_ports();
        return json_encode(['status' => 'ok']);
    }

}


