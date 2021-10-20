The presence of the following files do the following:
    DISABLE_HW - Emulate I/O boards, modem ant etc.
    GUARD_TESTING - enable test mode for guard system

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

Settings:
    Settings store in settings.php. This file is not under git.
    Default file located in default_configs directory.



Run script as daemon:
    nohup ./script.sh &>/dev/null &