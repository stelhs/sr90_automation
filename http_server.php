#!/usr/bin/php
<?php
chdir(dirname($argv[0]));

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'config.php';
require_once 'common_lib.php';


$log = new Plog('sr90:http_server');
$php_errors = '';

function printlog($data)
{
    file_put_contents("http_log.txt", print_r($data, 1) . "\n", FILE_APPEND);
}


function parse_http($http_content)
{
    global $log;
    $data_start = strpos($http_content, "\r\n\r\n");
    if ($data_start === false) {
        $log->err("Http parsing error: Empty line has not found. content: %s",
                    $http_content);
        return NULL;
    }

    $data_start += 4;

    $header_text = substr($http_content, 0, $data_start);

    $http_data = '';
    if ((strlen($http_content) - 1) > $data_start)
        $http_data = substr($http_content, $data_start);

    $cnt = 0;
    $http = [];
    $rows = explode("\r\n", $header_text);
    if (!count($rows)) {
        $log->err("Http header parsing error. Http header: %s",
                  $header_text);
        return NULL;
    }

    foreach ($rows as $row) {
        $row = trim($row);
        if (!$row)
            continue;

        $cnt++;
        if ($cnt == 1) {
            $http['query'] = $row;
            continue;
        }

        $tokens = explode(":", $row);
        if ((!isset($tokens[0])) || (!isset($tokens[1])))
            continue;

        $http[trim($tokens[0])] = trim($tokens[1]);
    }
    $http['data'] = $http_data;

    return $http;
}

function stdin_get_http_query()
{
    $http_content = '';
    $f = fopen('php://stdin', 'r');

    $content_length = false;
    while($line = fgets($f)) {
        $http_content .= $line;

        $parts = explode(":", $line);
        $var = trim($parts[0]);
        $val = isset($parts[1]) ? trim($parts[1]) : '';
        if ($var == 'Content-Length') {
            $content_length = $val;
            continue;
        }

        if ($line == "\r\n") {
            if ($content_length)
                $http_content .= fread($f, $content_length);
            break;
        }
    }
    fclose($f);

    return $http_content;
}

function return_ok($d = "")
{
    $str = '';
    $str .= "HTTP/1.1 200 OK\n";
    $str .= sprintf("Content-Type: text/plain\n");
    $str .= sprintf("Content-Length: %s\n", strlen($d) + 1);
    $str .= "\n\n";
    $str .= $d;
    return $str;
}

function return_bad_request($d = "")
{
    $str = '';
    $str .= "HTTP/1.1 400 Bad Request\n";
    $str .= sprintf("Content-Type: text/plain\n");
    $str .= sprintf("Content-Length: %s\n", strlen($d) + 1);
    $str .= "\n\n";
    $str .= $d;
    return $str;
}

function return_404_request()
{
    $c = "404 Page not found\n";
    $str = '';
    $str .= "HTTP/1.1 404 Page Not Found\n";
    $str .= sprintf("Content-Type: text/plain\n");
    $str .= sprintf("Content-Length: %s\n", strlen($c) + 1);
    $str .= "\n\n";
    $str .= $c;
    return $str;
}


function do_handle_request($method, $query, $remote_host)
{
    $url_parts = parse_url($query);
    foreach (http_handlers() as $handler) {
        foreach ($handler->requests() as $rp => $params) {
            if ($rp != $url_parts['path'])
                continue;

            if (!isset($params['method']) or
                $params['method'] != $method)
                continue;

            $args_list = [];
            if (isset($url_parts['query'])) {
                parse_str($url_parts['query'], $data);
                foreach ($data as $key => $value)
                    $args_list[strtolower($key)] = strtolower($value);
            }

            if (isset($params['required_args']) and
                    count($params['required_args'])) {
                $fail = false;
                foreach ($params['required_args'] as $arg)
                    if (!isset($args_list[$arg]))
                        $fail = true;
                if ($fail)
                    continue;
            }

            $f = $params['handler'];
            return $handler->$f($args_list, $remote_host, $query);
        }
    }
    return NULL;
}

function php_err_handler($errno, $str, $file, $line) {
    global $php_errors;
    $php_errors .= sprintf("PHP %s: %s in %s:%s \n %s \n",
            errno_to_str($errno), $str, $file, $line, backtrace_to_str(1));

}

function main($argv)
{
    global $log;
    global $php_errors;

    set_error_handler('php_err_handler');
    error_reporting(0);
    p_disable();

    $remote_host = getenv("REMOTE_HOST");

    $http_query_text = stdin_get_http_query();
    $http_data = parse_http($http_query_text);
    if (!$http_data) {
        $log->err("Can't parse http request from %s, request: %s\n",
                  $remote_host, $http_query_text);
        return_404_request();
        return 0;
    }

    $words = preg_split('/\s+/', $http_data['query']);
    $method = $words[0];
    $query = $words[1];

    $ret = do_handle_request($method, $query, $remote_host);
    if (!$ret)
        $stdout = return_404_request();
    else
        $stdout = return_ok($ret);

    echo $stdout;
    fclose(STDOUT);

    if ($php_errors) {
        plog(LOG_ERR, 'sr90:http_server', $php_errors);
        tn()->send_to_admin("sr90:http_server: %s", $php_errors);
    }
}

exit(main($argv));
