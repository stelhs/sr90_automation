The presence of the following files do the following:
    DISABLE_HW - Emulate I/O boards, modem ant etc.
    GUARD_TESTING - enable test mode for guard system
    HIDE_TELEGRAM - redirect sended all public messages to admin chat

Display system status
    ./stat.php

Display periodically task status
    ./periodically.php stat

Debug specific task:
    ./periodically.php run <task_name>

Debudding telegram commands:
    1) disable telegram task on sr90 server:
        ./periodically.php stop telegram
    2) getting last telegram_update id
        cat ~/.telegram_last_rx_update_id
    3) set telegram_update id on the test machine:
        echo 186089176 >  ~/.telegram_last_rx_update_id
    4) start telegram task on the test machine
        ./periodically.php start telegram

Start all periodically tasks:
    ./periodically.php start

Cron settings:
    every min:
        ./cron.php min
    every hour:
        ./cron.php hour
    every day:
        ./cron.php day

    It is possible to run cron.php with specific handler_name for debug:
        ./cron.php min lighters


Generate IO action:
./io.php trig <port_name> <state>
    Example: ./io.php trig remote_guard_sleep 1

Settings:
    Settings store in settings.php. This file is not under git.
    Default file located in default_configs directory.



Run script as daemon:
    nohup ./script.sh &>/dev/null &