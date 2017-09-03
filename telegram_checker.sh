#/bin/bash

cd /root/sr90_automation

while [ 1 ]
do
     ./starter.php 90 ./telegram.php msg_recv ./make_telegram_actions.php
     sleep 1
done
