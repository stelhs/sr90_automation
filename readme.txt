The presence of the following files do the following:
    DISABLE_HW - Emulate I/O boards, modem ant etc.
    HIDE_SOUND - disable audio notifications
    HIDE_TELEGRAM - disable sending to public telegram channels
    HIDE_SMS - send SMS only to admin

Display system status
    ./stat.php

Display periodically task status
    ./periodically.php stat

Debug specific task:
    ./periodically.php run <task_name>


Start all periodically tasks:
    ./periodically.php start

Cron settings:
    every min:
        ./cron.php min
    every hour:
        ./cron.php hour
    every day:
        ./cron.php day

Generate IO action:
./io.php trig <port_name> <state>
    Example: ./io.php trig remote_guard_sleep 1


Run script as daemon:
    nohup ./script.sh &>/dev/null &