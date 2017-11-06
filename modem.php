#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'config.php';
require_once 'modem3g.php';
require_once 'common_lib.php';
$utility_name = $argv[0];


function print_help()
{
    global $utility_name;
    echo "Usage: $utility_name <command> <args>\n" .
    	     "\tcommands:\n" .
    		 "\tsms_send - Send SMS\n" .
             "\t\tExample:\n" .
             "\t\t\t $utility_name sms_send +375295051024 'test test'\n" .
    		 "\tsms_send_users - Send SMS for all users subscribed to 'serv_control'\n" .
             "\t\tExample:\n" .
             "\t\t\t $utility_name sms_send_users 'test test'\n" .
    		 "\tsms_recv <action_script> - Attempt to receive SMS and run <action_script> for each\n" .
    		 "\t\tExample:\n" .
             "\t\t\t $utility_name sms_recv make_sms_actions.php\n" .
    		 "\tussd_send - Send USSD\n" .
             "\t\tExample:\n" .
             "\t\t\t $utility_name ussd_send *100#\n" .
    "\n\n";
}


function main($argv)
{
    $rc = 0;
    if (!isset($argv[1])) {
        return -EINVAL;
    }

    $modem = new Modem3G(conf_modem()['ip_addr']);

    $cmd = strtolower($argv[1]);
    switch ($cmd) {
    case 'sms_send':
        $phone = $argv[2];
        $text = $argv[3];

        $ret = $modem->send_sms($phone, $text);
        if ($ret) {
            perror("Can't send SMS: %s\n", $ret);
            $rc = -EBUSY;
        }
        goto out;

    case 'sms_send_users':
        $text = $argv[2];

        // get list phones for SMS subscribers
        $users = db()->query_list('SELECT * FROM users '.
                                  'WHERE serv_control = 1');
        $list_phones = array();
        foreach ($users as $user)
            $list_phones[] = string_to_array($user['phones'])[0];

        foreach ($list_phones as $phone) {
            $ret = $modem->send_sms($phone, $text);
            if ($ret) {
                perror("Can't send SMS: %s\n", $ret);
                $rc = -EBUSY;
            }
        }
        goto out;

    case 'sms_recv':
        $ret = $modem->check_for_new_sms();
        if (!is_array($ret)) {
            perror("Can't check for new sms: \n", $ret);
            $rc = -EBUSY;
            goto out;
        }

        if (!count($ret)) {
            perror("No new SMS receved\n");
            goto out;
        }

        $action_script = "";
        if (isset($argv[2]))
            $action_script = $argv[2];

        perror("New SMS was received:\n");
        foreach ($ret as $sms) {
            perror("\tDate: %s\n", $sms['date']);
            perror("\tPhone: %s\n", $sms['phone']);
            perror("\tMessage: %s\n\n", $sms['text']);
            if (!$action_script)
                continue;

            run_cmd(sprintf("%s '%s' '%s' '%s'",
                            $action_script, $sms['date'],
                            $sms['phone'], $sms['text']));
        }

        goto out;

    case 'ussd_send':
        $text = $argv[2];

        perror("sending $text\n");
        $ret = $modem->send_ussd($text);
        if ($ret) {
            perror("Can't send USSD: %s\n", $ret);
            $rc = -EBUSY;
            goto out;
        }

        perror("waiting for response\n");
        for (;;) {
            sleep(1);
            $response = $modem->check_for_new_ussd();
            if ($response < 0)
                continue;

            perror("%s\n", $response);
            break;
        }

        goto out;

    default:
        $rc = -EINVAL;
    }

out:
    return $rc;
}

$rc = main($argv);
if ($rc) {
    print_help();
    exit($rc);
}
