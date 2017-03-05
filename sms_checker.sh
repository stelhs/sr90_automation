#/bin/bash

while [ 1 ]
do
    ./modem.php sms_recv make_sms_actions.php
    sleep 1
done