#/bin/bash

cd /root/sr90_automation

while [ 1 ]
do
     ./telegram.php msg_recv ./make_telegram_actions.php
     sleep 1
done
