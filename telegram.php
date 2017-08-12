#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'config.php';
require_once 'telegram_api.php';
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
    
    $db = new Database;
    $rc = $db->connect(conf_db());
    if ($rc) {
        printf("can't connect to database");
        return -EBASE;
    }

    $telegram = new Telegram_api;
    
    $telegram->post_request('getUpdates');
    
out:
    return $rc;
}

$rc = main($argv);
if ($rc) {
    print_help();
}
    