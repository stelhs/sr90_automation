<?php

require_once 'config.php';
require_once 'modem3g.php';

function serv_ctrl_send_sms($type, $args)
{
    switch ($type) {
    case 'reboot_sms':
        $sms_text = sprintf("Сервер ушел на перезагрузку по запросу SMS");
        break;
        
    case 'status':
        switch ($args['guard_state']) {
        case 'sleep':
            $args['guard_state'] = "отключена";
            break;
            
        case 'ready':
            $args['guard_state'] = "включена";
            break;
        }
        $sms_text = sprintf("Охрана: %s, Баланс счета: %s, Уровень сигнала: %s, uptime: %s", 
                            $args['guard_state'],
                            $args['balance'],
                            $args['radio_signal_level'],
                            $args['uptime']);
        break;
        
    default: 
        return -EINVAL;
    }
    
    $modem = new Modem3G(conf_modem()['ip_addr']);
    
    foreach (conf_global()['phones'] as $phone) {
        $ret = $modem->send_sms($phone, $sms_text);
        if ($ret) {
            msg_log(LOG_ERR, "Can't send SMS: " . $ret);
            return -EBUSY;
        }
    }
}

function get_day_night()
{
    $sun_info = date_sun_info(time(), 54.014634, 28.013484);
    $curr_time = time();

    if ($curr_time > $sun_info['nautical_twilight_begin'] && 
        $curr_time < $sun_info['nautical_twilight_end'])
            return 'day';

    return 'night';
}

