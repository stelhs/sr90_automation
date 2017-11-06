#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';

define("MSG_LOG_LEVEL", LOG_NOTICE);

/* Calling by Usio daemon */
function main($argv) {
    if (count($argv) < 3) {
        perror("incorrect arguments\n");
        return;
    }

    $action_port = $argv[1];
    $action_state = $argv[2];

    $content = file_get_contents(sprintf("http://localhost:400/ioserver" .
                                         "?io=usio1&port=%d&state=%d",
                                         $action_port,
                                         $action_state));
    pnotice("returned content: %s\n", $content);
}


exit(main($argv));