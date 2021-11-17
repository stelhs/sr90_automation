<?php

require_once '/usr/local/lib/php/xml.php';

require_once 'common_lib.php';

class Modem3G {
    private $ip_addr;

    function __construct($ip_addr)
    {
        $this->ip_addr = $ip_addr;
        $this->log = new Plog('sr90:modem3g');
    }


    function post_request($url, $request)
    {
        $full_url = sprintf('http://%s%s', $this->ip_addr, $url);

        $query = '<?xml version="1.0" encoding="UTF-8"?>' .
                 '<request>' . $request . '</request>';

        $options = array(
            'http' => array(
                'protocol_version' => 1.1,
                'header'  => "Connection: close\r\n" .
                             "Content-Type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => $query,
            )
        );
        $context = stream_context_create($options);
        @$result = file_get_contents($full_url, false, $context);
        if ($result == FALSE) {
            $this->log->err("Can't make post_request()");
            return -EPARSE;
        }

        return parse_xml($result);
    }


    function request($url)
    {
        $full_url = sprintf('http://%s%s', $this->ip_addr, $url);

        $result = file_get_contents($full_url);
        if ($result == FALSE) {
            $this->log->err("Can't make GET request()");
            return -EPARSE;
        }

    	return parse_xml($result);
    }


    function send_sms($pnone_number, $text)
    {
        if (DISABLE_HW) {
            perror("send_sms to %s, len=%d: %s\n",
                    $pnone_number, strlen($text), $text);
            return 0;
        }

        // remove stored outgoing sms
        $rows = $this->sms_list(2);
        if (is_array($rows) && count($rows)) {
            foreach ($rows as $row) {
                $sms_index = $row['content']['Index'][0]['content'];
                perror("remove outgoing sms index=%d\n", $sms_index);
                $this->remove_sms($sms_index);
            }
        }

        $query =  '<Index>-1</Index>' .
                  '<Phones>' .
                      '<Phone>' . $pnone_number . '</Phone>' .
                  '</Phones>' .
                  '<Content>' . $text . '</Content>' .
                  '<Length>' . strlen($text) . '</Length>' .
                  '<Reserved>0</Reserved>' .
                  '<Date>111</Date>';

        $data = $this->post_request('/api/sms/send-sms', $query);
        if ($data < 0) {
            $this->log->err("Can't send SMS %s test: %s reason: Can`t make POST request",
                        $pnone_number, $text);
            return $data;
        }

        if (isset($data['response']['content'][0]) && $data['response']['content'][0] == 'OK')
            return 0;

        $err = $data['error']['content']['code'][0]['content'];
        $this->log->err("Can't send SMS %s test: %s reason: modem responce error: %s",
                        $pnone_number, $text, $err);
        return $err;
    }


    function send_ussd($text)
    {
        if (DISABLE_HW) {
            perror("modem.send_ussd %s\n", $text);
            return 0;
        }

        $query = '<content>' . $text . '</content>' .
                 '<codeType>CodeType</codeType>';

        $data = $this->post_request('/api/ussd/send', $query);
        if ($data < 0) {
            $this->log->err("Can't send USSD %s reason: can't make POST request\n", $text);
            return -EPARSE;
        }

        if (isset($data['response']['content'][0]) && $data['response']['content'][0] == 'OK') {
            return 0;
        }

        $this->log->err("Modem: Can't send USSD %s reason code: %s\n",
                        $text, $data['error']['content']['code'][0]['content']);
        return $data['error']['content']['code'][0]['content'];
    }


    function new_ussd()
    {
        if (DISABLE_HW) {
            perror("modem.new_ussd\n");
            return 0;
        }

        $data = $this->request('/api/ussd/get');
        if ($data < 0) {
            $this->log->err("new_ussd(): Can't make GET request");
            return $data;
        }

        if (isset($data['error']['content']['code'][0]['content'])) {
            $err = $data['error']['content']['code'][0]['content'];
            $this->log->err("modem response error: %s", $err);
            return $err;
        }

        return $data['response']['content']['content'][0]['content'];
    }


    function remove_sms($sms_index)
    {
        if (DISABLE_HW) {
            perror("modem.remove_sms %d\n", $sms_index);
            return 0;
        }

        $query = '<Index>' . $sms_index . '</Index>';

        $data = $this->post_request('/api/sms/delete-sms', $query);
        if ($data < 0) {
            $this->log->err("Modem: Can`t remove SMS: can`t make POST request\n");
            return -EPARSE;
        }

        if (isset($data['error']['content']['code'][0]['content'])) {
            $err = $data['error']['content']['code'][0]['content'];
            $this->log->err("remove_sms(): modem response error: %s", $err);
            return $err;
        }

        if (isset($data['response']['content'][0]) && $data['response']['content'][0] == 'OK')
            return 0;


        $this->log->err("remove_sms(): modem not response correct code");
        return -EPARSE;
    }

    function sms_list($box_type)
    {
        if (DISABLE_HW) {
            perror("modem.get_sms_list\n");
            return [];
        }

        // $box_type: 1 - incomming, 2 - outgoing

        $query =    '<PageIndex>1</PageIndex>' .
                    '<ReadCount>50</ReadCount>' .
                    '<BoxType>' . $box_type . '</BoxType>' .
                    '<SortType>0</SortType>' .
                    '<Ascending>0</Ascending>' .
                    '<UnreadPreferred>0</UnreadPreferred>';

        $data = $this->post_request('/api/sms/sms-list', $query);
        if ($data < 0) {
            $this->log->err("Modem: Can`t check SMS list reason: can`t make POST request\n");
            return -EPARSE;
        }

        if (isset($data['error']['content']['code'][0]['content'])) {
            $err = $data['error']['content']['code'][0]['content'];
            $this->log->err("sms_list(): modem response error: %s", $err);
            return $err;
        }

        $count_sms = $data['response']['content']['Count'][0]['content'];
        if (!$count_sms)
            return [];

        return $data['response']['content']['Messages'][0]['content']['Message'];
    }

    function new_sms()
    {
        if (DISABLE_HW) {
            @$content = file_get_contents('fake_rx_sms.txt');
            if (!$content)
                return [];

            $lines = string_to_words($content, "\n");
            $list = [];
            $updated_content = '';
            foreach ($lines as $row) {
                if (!trim($row))
                    continue;

                $words = string_to_words($row, " \t:;-=");
                pnotice("fake sms row: %s\n", $row);

                $mode = array_shift($words);
                $phone = array_shift($words);
                $date = date('Y-m-d H:m:s');
                $text = array_to_string($words, ' ');

                $updated_content .= sprintf("0 %s %s\n", $phone, $text);

                if ($mode != '1')
                    continue;

                $list[] = ['phone' => $phone,
                           'text' => $text,
                           'date' => $date];
            }
            file_put_contents('fake_rx_sms.txt', $updated_content);
            return  $list;
        }

        $rows = $this->sms_list(1);
        if (!is_array($rows))
            return $rows;

        if (!count($rows))
            return [];

        $sms_list = [];
        $row_num = 0;
        foreach ($rows as $row) {
            $sms_list[$row_num]['phone'] = $row['content']['Phone'][0]['content'];
            $sms_list[$row_num]['text'] = $row['content']['Content'][0]['content'];
            $sms_list[$row_num]['date'] = $row['content']['Date'][0]['content'];

            $sms_index = $row['content']['Index'][0]['content'];
            $this->remove_sms($sms_index);
            $row_num++;
        }

        return $sms_list;
    }

    function status()
    {
        if (DISABLE_HW) {
            perror("modem.get_status\n");
            $info = [];
            $info['connection_status'] = '';
            $info['signal_strength'] = '';
            $info['signal_icon'] = '';
            $info['cur_net_type'] = '';
            $info['wan_ip_addr'] = '';
            $info['primary_dns'] = '';
            $info['secondary_dns'] = '';
            return $info;
        }

        $data = $this->request('/api/monitoring/status');
        if ($data < 0) {
            $this->log->err("Modem: Can`t check modem status: can`t make POST request\n");
            return $data;
        }

        if (isset($data['error']['content']['code'][0]['content'])) {
            $err = $data['error']['content']['code'][0]['content'];
            $this->log->err("status(): modem response error: %s", $err);
            return $err;
        }

        $info = [];
        $info['connection_status'] = $data['response']['content']['ConnectionStatus'][0]['content'];
        $info['signal_strength'] = $data['response']['content']['SignalStrength'][0]['content'];
        $info['signal_icon'] = $data['response']['content']['SignalIcon'][0]['content'];
        $info['cur_net_type'] = $data['response']['content']['CurrentNetworkType'][0]['content'];
        $info['wan_ip_addr'] = $data['response']['content']['WanIPAddress'][0]['content'];
        $info['primary_dns'] = $data['response']['content']['PrimaryDns'][0]['content'];
        $info['secondary_dns'] = $data['response']['content']['SecondaryDns'][0]['content'];
        return $info;
    }

    function traffic_statistics()
    {
        if (DISABLE_HW) {
            perror("modem.get_traffic_statistics\n");
            $info = [];
            $info['curr_connect_time'] = '';
            $info['curr_upload'] = '';
            $info['curr_download'] = '';
            $info['curr_download_rate'] = '';
            $info['curr_upload_rate'] = '';
            $info['total_upload'] = '';
            $info['total_download'] = '';
            $info['total_connect_time'] = '';
            return $info;
        }

        $data = $this->request('/api/monitoring/traffic-statistics');
        if ($data < 0) {
            $this->log->err("traffic_statistics(): can`t make POST request\n");
            return $data;
        }

        if (isset($data['error']['content']['code'][0]['content'])) {
            $err = $data['error']['content']['code'][0]['content'];
            $this->log->err("traffic_statistics(): modem response error: %s", $err);
            return $err;
        }

        $info = [];
        $info['curr_connect_time'] = $data['response']['content']['CurrentConnectTime'][0]['content'];
        $info['curr_upload'] = $data['response']['content']['CurrentUpload'][0]['content'];
        $info['curr_download'] = $data['response']['content']['CurrentDownload'][0]['content'];
        $info['curr_download_rate'] = $data['response']['content']['CurrentDownloadRate'][0]['content'];
        $info['curr_upload_rate'] = $data['response']['content']['CurrentUploadRate'][0]['content'];
        $info['total_upload'] = $data['response']['content']['TotalUpload'][0]['content'];
        $info['total_download'] = $data['response']['content']['TotalDownload'][0]['content'];
        $info['total_connect_time'] = $data['response']['content']['TotalConnectTime'][0]['content'];
        return $info;
    }

    function reset_traffic_statistics()
    {
        if (DISABLE_HW) {
            perror("modem.reset_traffic_statistics\n");
            return 0;
        }

        $query = '<ClearTraffic>1</ClearTraffic>';

        $data = $this->post_request('/api/monitoring/clear-traffic', $query);
        if ($data < 0) {
            $this->log->err("Modem: Can`t reset traffic statistics: can`t make POST request\n");
            return -EPARSE;
        }

        if (isset($data['error']['content']['code'][0]['content'])) {
            $err = $data['error']['content']['code'][0]['content'];
            $this->log->err("traffic_statistics(): modem response error: %s", $err);
            return $err;
        }

        if (isset($data['response']['content'][0]) && $data['response']['content'][0] == 'OK')
            return 0;

        $this->log->err("reset_traffic_statistics(): modem not response correct code");
        return -EPARSE;
    }


    function check_sended_sms_status()
    {
        if (DISABLE_HW) {
            perror("modem.check_sended_sms_status\n");
            $info = [];
            $info['curr_phone'] = '';
            $info['success_phone'] = '';
            $info['fail_phone'] = '';
            $info['total_cnt'] = '';
            $info['curr_index'] = '';
            return $info;
        }

        $data = $this->request('/api/sms/send-status');
        if ($data < 0) {
            $this->log->err("check_sended_sms_status(): can`t make POST request\n");
            return $data;
        }

        if (isset($data['error']['content']['code'][0]['content'])) {
            $err = $data['error']['content']['code'][0]['content'];
            $this->log->err("traffic_statistics(): modem response error: %s", $err);
            return $err;
        }

        $info = [];
        $info['curr_phone'] = $data['response']['content']['Phone'][0]['content'];
        $info['success_phone'] = $data['response']['content']['SucPhone'][0]['content'];
        $info['fail_phone'] = $data['response']['content']['FailPhone'][0]['content'];
        $info['total_cnt'] = $data['response']['content']['TotalCount'][0]['content'];
        $info['curr_index'] = $data['response']['content']['CurIndex'][0]['content'];
        return $info;
    }

    function sim_balanse()
    {
        if (DISABLE_HW) {
            perror("modem.get_sim_balanse\n");
            return '15р';
        }

        $ret = $this->send_ussd('*100#');
        if ($ret) {
            $this->log->err("Can't get Balanse: %s\n", $ret);
            return -EBUSY;
        }

        for ($i = 0; $i < 5; $i++) {
            sleep(3);
            $response = $this->new_ussd();
            if ($response < 0)
                continue;

            break;
        }

        if ($response < 0) {
            $this->log->err("Modem responce incorrect SIM balance: %s", $response);
            return -ECONNFAIL;
        }

        preg_match('/Balans\=([\w\.]+)/m', $response, $mathes);
        if (!isset($mathes[1])) {
            $this->log->err("Can't parse SIM balance response: %s", $response);
            return -EPARSE;
        }

        return $mathes[1];
    }

    function send_sms_to_user($user_id, $text)
    {
        $user = user_by_id($user_id);
        if (!$user)
            return;
        foreach ($user['phones'] as $phone)
            modem3g()->send_sms($phone, $text);
        return;
    }

    function send_sms_alarm($text)
    {
        $users = users_for_alarm();
        foreach ($users as $user)
            foreach ($user['phones'] as $phone)
                modem3g()->send_sms($phone, $text);
    }

    function stat_text()
    {
        $balance_tg = $balance_sms = $this->sim_balanse();
        if ($balance_tg < 0) {
            $balance_tg = sprintf('не удалось получить, код: %s', $balance_tg);
            $balance_sms = 'неработет';
        }
        $tg = sprintf("Баланс счета SIM карты: %s\n", $balance_tg);
        $sms = sprintf("Баланс:%s, ", $balance_sms);
        return [$tg, $sms];
    }
}

function modem3g()
{
    static $modem = NULL;
    if (!$modem)
        $modem = new Modem3g(conf_modem()['ip_addr']);
    return $modem;
}



class Modem_tg_events implements Tg_skynet_events {
    function name()
    {
        return "modem3g";
    }

    function cmd_list() {
        return [
            ['cmd' => ['переключи на основной модем',
                       'modem 2'],
             'method' => 'primary_modem'],

            ['cmd' => ['переключи на вспомогательный модем',
                       'modem 1'],
             'method' => 'secondary_modem'],
            ];
    }


    function primary_modem($chat_id, $msg_id, $user_id, $arg, $text)
    {
        if (!DISABLE_HW) {
            run_cmd("./inet_switch.sh 2");
            run_cmd("killall -9 ssh");
            sleep(3);
        }
        tn()->send($chat_id, $msg_id, "Готово. Переключен на основной модем");
    }

    function secondary_modem($chat_id, $msg_id, $user_id, $arg, $text)
    {
        if (!DISABLE_HW) {
            run_cmd("./inet_switch.sh 1");
            run_cmd("killall -9 ssh");
            sleep(3);
        }
        tn()->send($chat_id, $msg_id, 'Готово. Переключен на вспомогательный модем');
    }
}

class Modem3g_periodically implements Periodically_events {
    function __construct()
    {
        $this->log = new Plog('sr90:Modem3g_periodically');
    }

    function name()
    {
        return "modem3g";
    }

    function interval()
    {
        return 1;
    }

    function do() {
        $list = modem3g()->new_sms();
        if (!is_array($list)) {
            $this->log->err("Can't check for new sms: %s\n", $list);
            $rc = -EBUSY;
            return;
        }

        if (!count($list))
            return;

        perror("New SMS was received:\n");
        foreach ($list as $sms) {
            perror("\tDate: %s\n", $sms['date']);
            perror("\tPhone: %s\n", $sms['phone']);
            perror("\tMessage: %s\n\n", $sms['text']);

            $phone = $sms['phone'];
            $text = trim($sms['text']);
            $sms_date = $sms['date'];

            $user_id = 0;
            $user = user_get_by_phone($phone);
            if ($user)
                $user_id = $user['id'];

            $this->log->info("incomming a new sms: %s: %s",
                             $phone, $text);
            $row_id = db()->insert('incomming_sms',
                                  ['phone' => $phone,
                                   'text' => $text,
                                   'received_date' => $sms_date]);
            if (!$row_id)
                $this->log->err("Can't insert into incomming_sms");

            foreach (sms_handlers() as $handler) {
                foreach ($handler->cmd_list() as $row) {
                    foreach($row['cmd'] as $cmd) {
                        if ($cmd == $text) {
                            $public = false;
                            if (isset($row['public']) and $row['public'] == true)
                                $public = true;

                            if ($user_id == 0 && !$public) {
                                tn()->send_to_admin("Ктото с номера %s отправил SMS с командой: %s\n",
                                                    $phone, $text);
                                return;
                            }

                            $arg = NULL;
                            if (isset($row['arg']))
                                $arg = $row['arg'];

                            $f = $row['method'];
                            $handler->$f($phone, $user, $arg, $text);
                            return;
                        }
                    }
                }
            }
            modem3g()->send_sms($phone, sprintf('Неизвестная команда: %s', $text));
        }
    }
}


class Inet_sms_events implements Sms_events {
    function __construct()
    {
        $this->log = new Plog('sr90:Modem3g_sms_event');
    }

    function name()
    {
        return "inet";
    }

    function cmd_list() {
        return [
            ['cmd' => ['inet primary'],
             'method' => 'primary_modem'],

            ['cmd' => ['inet secondary'],
             'method' => 'secondary_modem'],
            ];
    }

    function primary_modem($phone, $user, $arg, $text)
    {
        modem3g()->send_sms($phone, 'Интернет переключен на основной модем');
        if (!DISABLE_HW) {
            run_cmd("./inet_switch.sh 2");
            run_cmd("killall -9 ssh");
            sleep(3);
        }
        $this->log->info("Internet switched to primary modem");
        tn()->send_to_admin("Переключен на основной модем");

    }

    function secondary_modem($phone, $user, $arg, $text)
    {
        modem3g()->send_sms($phone, 'Интернет переключен на вспомогательный модем');
        if (!DISABLE_HW) {
            run_cmd("./inet_switch.sh 1");
            run_cmd("killall -9 ssh");
            sleep(3);
        }
        $this->log->info("Internet switched to secondary modem");
        tn()->send_to_admin('Переключен на вспомогательный модем');
    }
}


