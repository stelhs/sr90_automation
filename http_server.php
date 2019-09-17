#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';
require_once 'common_lib.php';


$log_file = "/root/log.txt";
$http_log = [];

function printlog($data)
{
    global $log_file;
    file_put_contents($log_file, print_r($data, 1) . "\n", FILE_APPEND);
}

function parse_http($http_content)
{
    $data_start = strpos($http_content, "\r\n\r\n");
    if ($data_start === false)
        return -1;

    $data_start += 4;

    $header_text = substr($http_content, 0, $data_start);

    $http_data = '';
    if ((strlen($http_content) - 1) > $data_start)
        $http_data = substr($http_content, $data_start);

    $cnt = 0;
    $http = array();
    $rows = explode("\r\n", $header_text);
    foreach ($rows as $row) {
        $row = trim($row);
        if (!$row)
            continue;

        $cnt++;
        if ($cnt == 1) {
            $http['_query'] = $row;
            continue;
        }

        $tokens = explode(":", $row);
        if ((!isset($tokens[0])) || (!isset($tokens[1])))
            continue;

        $http[trim($tokens[0])] = trim($tokens[1]);
    }
    $http['_data'] = $http_data;

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

function return_ok($data_text = "")
{
    global $http_log;
    echo "HTTP/1.1 200 OK\n";
    echo sprintf("Content-Type: text/plain\n");
    echo sprintf("Content-Length: %s\n", strlen($data_text) + 1);
    echo "\n\n";
    echo $data_text;
    $http_log['return'] = addslashes($data_text);
    http_end();
}

function return_bad_request($data_text = "")
{
    global $http_log;
    echo "HTTP/1.1 400 Bad Request\n";
    echo sprintf("Content-Type: text/plain\n");
    echo sprintf("Content-Length: %s\n", strlen($data_text) + 1);
    echo "\n\n";
    echo $data_text;
    $http_log['return'] = "bad_request";
    http_end();
}

function return_404_request()
{
    global $http_log;
    $content = "404 Page not found\n";
    echo "HTTP/1.1 404 Page Not Found\n";
    echo sprintf("Content-Type: text/plain\n");
    echo sprintf("Content-Length: %s\n", strlen($content) + 1);
    echo "\n\n";
    echo $content;
    $http_log['return'] = "404";
    http_end();
}

function http_end()
{
    global $http_log;
    $rc = db()->insert("http_server_log", $http_log);
    exit;
}

function main($argv)
{
    global $http_log;
    chdir(dirname($argv[0]));
    $remote_host = getenv("REMOTE_HOST");

    $http_query_text = stdin_get_http_query();
    $http_data = parse_http($http_query_text);

    $words = preg_split('/\s+/', $http_data['_query']);
    $query = $words[1];

    $url_parts = parse_url($query);
    $path = str_replace('/', '', $url_parts['path']);
    $parts = string_to_array($path, '/');

    $http_log['remote_host'] = $remote_host;
    $http_log['query'] = $query;
    if (!count($parts))
        return_404_request();

    $php_file = sprintf('http_page_%s.php', $parts[0]);
    if (!file_exists($php_file))
        return_404_request();
    $http_data['script'] = $php_file;

    $query = '';
    if (isset($url_parts['query']))
        $query = $url_parts['query'];

    $script = sprintf('./%s "%s"', $php_file, $query);
    unset($parts[0]);
    foreach ($parts as $part)
        $script .= ' ' . $part;

    $ret = run_cmd($script);
    if ($ret['rc']) {
        $reason = sprintf("HTTP Server: script %s return error: %s\n",
                                 $script, $ret['log']);

        return_ok(json_encode(['status' => $ret['rc'],
                               'reason' => $reason]));

    }
    $ret_data = json_decode($ret['log'], true);
    if (!is_array($ret_data)) {
        return_ok(json_encode(['status' => 'ok',
            'log' => $ret['log']], JSON_UNESCAPED_UNICODE));
    }
    if (!isset($ret_data['status']))
        $ret_data['status'] = 'ok';
        return_ok(json_encode($ret_data, JSON_UNESCAPED_UNICODE));
}

exit(main($argv));
