<?php

class Modem3G {
    private $ip_addr;

    function __construct($ip_addr)
    {
        $this->ip_addr = $ip_addr;
    }


    function post_request($url, $request)
    {
        $full_url = 'http://' . $this->ip_addr . $url;

        $query = '<?xml version="1.0" encoding="UTF-8"?>' .
                 '<request>' . $request . '</request>';

        $options = array(
            'http' => array(
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => $query,
            )
        );
        $context = stream_context_create($options);
        @$result = file_get_contents($full_url, false, $context);
        if ($result == FALSE)
            return -EPARSE;

        return parse_xml($result);
    }


    function get_request($url)
    {
        $full_url = 'http://' . $this->ip_addr . $url;

        @$result = file_get_contents($full_url);
        if ($result == FALSE)
            return -EPARSE;

        return parse_xml($result);
    }


    function send_sms($pnone_number, $text)
    {
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
            msg_log(LOG_ERR, "Can't send SMS " . $pnone_number . 
                            ' text: ' . $text . ' reason: Can\'t connect to modem');
            return $data;
        }

        if (isset($data['response']['content'][0]) && $data['response']['content'][0] == 'OK')
            return 0;

        app_log(LOG_ERR, "Modem: Can't send SMS " . $pnone_number . 
                        ' text: ' . $text . ' reason code: ' . $data['error']['content']['code'][0]['content']);
        return $data['error']['content']['code'][0]['content'];
    }


    function send_ussd($text)
    {
        $query = '<content>' . $text . '</content>' .
                 '<codeType>CodeType</codeType>';

        $data = $this->post_request('/api/ussd/send', $query);
        if ($data < 0) { 
            msg_log(LOG_ERR, "Can't send USSD " . $text . 
                        ' reason: can\'t connect to modem');
            return -EPARSE;
        }

        if (isset($data['response']['content'][0]) && $data['response']['content'][0] == 'OK')
            return 0;

        app_log(LOG_ERR, "Modem: Can't send USSD " . $text . 
                    ' reason code: ' . $data['error']['content']['code'][0]['content']);
        return $data['error']['content']['code'][0]['content'];
    }


    function check_for_new_ussd()
    {
        $data = $this->get_request('/api/ussd/get');
        if ($data < 0)
            return $data;

        if (isset($data['error']['content']['code'][0]['content']))
            return $data['error']['content']['code'][0]['content'];

        return $data['response']['content']['content'][0]['content'];
    }


    function remove_sms($sms_index)
    {
        $query = '<Index>' . $sms_index . '</Index>';

        $data = $this->post_request('/api/sms/delete-sms', $query);
        if ($data < 0) {
            app_log(LOG_ERR, 'Modem: Can\'t remove SMS: can\'t connect to modem');
            return -EPARSE;
        }

        if (isset($data['error']['content']['code'][0]['content']))
            return $data['error']['content']['code'][0]['content'];

        if (isset($data['response']['content'][0]) && $data['response']['content'][0] == 'OK')
            return 0;

        return -EPARSE;
    }


    function check_for_new_sms()
    {
        $query =    '<PageIndex>1</PageIndex>' .
                    '<ReadCount>20</ReadCount>' .
                    '<BoxType>1</BoxType>' .
                    '<SortType>0</SortType>' .
                    '<Ascending>0</Ascending>' .
                    '<UnreadPreferred>0</UnreadPreferred>';

        $data = $this->post_request('/api/sms/sms-list', $query);
        if ($data < 0) {
            app_log(LOG_ERR, 'Modem: Can\'t check SMS list reason: can\'t connect to modem');
            return -EPARSE;
        }

        if (isset($data['error']['content']['code'][0]['content']))
            return $data['error']['content']['code'][0]['content'];

        $count_sms = $data['response']['content']['Count'][0]['content'];
        if (!$count_sms)
            return null;

        $rows = $data['response']['content']['Messages'][0]['content']['Message'];
        
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

    function get_global_status()
    {
        $data = $this->get_request('/api/monitoring/status');
        if ($data < 0)
            return $data;

        if (isset($data['error']['content']['code'][0]['content']))
            return $data['error']['content']['code'][0]['content'];

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

    function get_traffic_statistics()
    {
        $data = $this->get_request('/api/monitoring/traffic-statistics');
        if ($data < 0)
            return $data;

        if (isset($data['error']['content']['code'][0]['content']))
            return $data['error']['content']['code'][0]['content'];

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
        $query = '<ClearTraffic>1</ClearTraffic>';

        $data = $this->post_request('/api/monitoring/clear-traffic', $query);
        if ($data < 0) {
            app_log(LOG_ERR, 'Modem: Can\'t clear traffic statistics: can\'t connect to modem');
            return -EPARSE;
        }

        if (isset($data['error']['content']['code'][0]['content']))
            return $data['error']['content']['code'][0]['content'];

        if (isset($data['response']['content'][0]) && $data['response']['content'][0] == 'OK')
            return 0;

        return -EPARSE;
    }


    function check_sended_sms_status()
    {
        $data = $this->get_request('/api/sms/send-status');
        if ($data < 0)
            return $data;

        if (isset($data['error']['content']['code'][0]['content']))
            return $data['error']['content']['code'][0]['content'];

        $info = [];
        $info['curr_phone'] = $data['response']['content']['Phone'][0]['content'];
        $info['success_phone'] = $data['response']['content']['SucPhone'][0]['content'];
        $info['fail_phone'] = $data['response']['content']['FailPhone'][0]['content'];
        $info['total_cnt'] = $data['response']['content']['TotalCount'][0]['content'];
        $info['curr_index'] = $data['response']['content']['CurIndex'][0]['content'];
        return $info;
    }


}


?>