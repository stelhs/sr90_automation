#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'config.php';
require_once 'httpio_lib.php';
require_once 'guard_lib.php';
require_once 'sequencer_lib.php';
require_once 'common_lib.php';

$utility_name = $argv[0];

function print_help()
{
    global $utility_name;
    echo "Usage: $utility_name <args>\n" .
             "\t Play voice broadcast message.\n" .
             "\t\t Args: <text_message> [volume] [voice_type] [speed]\n" .
             "\t\t <text_message> - Played text message string\n" .
             "\t\t [volume] - Volume level in percent\n" .
             "\t\t [voice_type] - Voice type:\n" .
             "\t\t\t\t 1 - Aleksandr:\n" .
             "\t\t\t\t 2 - Anna:\n" .
             "\t\t\t\t 3 - Irina:\n" .
             "\t\t [speed] - Playback speed. 0 - normal speed. -0.9 minmal speed, 0.9 maximal speed:\n" .
             "\t\t example: $utility_name 'Привет страна' 100 1 -0.7\n" .
    "\n\n";
}


function main($argv)
{
    if (!isset($argv[1])) {
        print_help();
        return -EINVAL;
    }

    $message = trim($argv[1]);
    $volume = isset($argv[2]) ? (int)$argv[2] : 100;
    $voice_type = isset($argv[3]) ? (int)$argv[3] : 1;
    $speed = isset($argv[4]) ? (int)$argv[4] : 0;

    switch ($voice_type) {
        case 1:
            $voice_name = "Aleksandr+CLB";
            break;

        case 2:
            $voice_name = "Anna+CLB";
            break;

        case 3:
            $voice_name = "Irina+CLB";
            break;
        default:
            perror("Incorrect voice type\n");
            return -EINVAL;
    }


    $amplifier_port = conf_broadcast_audio()['amplifier_io'];
    // Enable amplifier
    httpio($amplifier_port['io'])->relay_set_state($amplifier_port['io_port'], 1);
    sleep(1);

    run_cmd("amixer -q set Master 100%");
    run_cmd("amixer -q set 'Speaker Boost' 100%");
    run_cmd("amixer -q set PCM unmute");
    run_cmd("amixer -q set Master unmute");
    run_cmd("amixer -q set 'Speaker Boost' unmute");

    run_cmd(sprintf("amixer -q set PCM %d%%", $volume));


    run_cmd(sprintf("export $(cat /tmp/dbus_vars);echo \"%s\" | RHVoice-client -s %s -r %s | aplay",
                    $message, $voice_name, $speed));


    // Disable amplifier
    httpio($amplifier_port['io'])->relay_set_state($amplifier_port['io_port'], 0);


    return 0;
}


exit(main($argv));

