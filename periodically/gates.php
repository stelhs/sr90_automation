#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'gates_api.php';


function main($argv)
{
    if (!file_exists(GATES_CLOSE_AFTER))
        return;

    @$after = (int)file_get_contents(GATES_CLOSE_AFTER);
    if (!$after)
        return;

    if (time() > $after)
        gates_close();
}

exit(main($argv));

