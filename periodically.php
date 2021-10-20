#!/usr/bin/php
<?php
chdir(dirname($argv[0]));

require_once '/usr/local/lib/php/database.php';
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';

require_once 'common_lib.php';
require_once 'gates_api.php';
require_once 'power_api.php';
require_once 'telegram_lib.php';
require_once 'modem3g.php';

$app_name = $argv[0];

function print_help()
{
    global $app_name;
    perror("Usage: $app_name <command> <args>\n" .
             "\tcommands:\n" .
                 "\t\t start [task_name] - Start all or specific tasks\n" .
                 "\t\t\texample start all: $app_name start\n" .
                 "\t\t\texample start specific: $app_name start gates\n" .

                 "\t\t stop [task_name] - Stop all or specific tasks\n" .
                 "\t\t\texample stop all: $app_name stop\n" .
                 "\t\t\texample stop specific: $app_name stop gates\n" .

                 "\t\t pause [task_name] - Pause all or specific task\n" .
                 "\t\t\texample pause all: $app_name pause\n" .
                 "\t\t\texample pause specific: $app_name pause gates\n" .

                 "\t\t resume [task_name] - Resume all or specific task\n" .
                 "\t\t\texample resume all: $app_name resume\n" .
                 "\t\t\texample resume specific: $app_name resume gates\n" .

                 "\t\t run <task_name> - Run specific task synchronously\n" .
                 "\t\t\texample: $app_name run gates\n" .

                 "\t\t stat - Display status information\n" .
                 "\t\t\texample: $app_name stat\n" .
             "\n\n");
}

function task_by_name($tname)
{
    foreach (periodically_list() as $task) {
        if ($task->name() == $tname)
            return $task;
    }
    return NULL;
}

function run_task($tname)
{
    $task = task_by_name($tname);
    pnotice("run task: %s\n", $task->name());
    $task->do();
}

function pid_file_by_task_name($tname)
{
    return sprintf('%s.task_%s_pid', PID_DIR, $tname);
}

function pause_file_by_task_name($tname)
{
    return sprintf('%s.task_%s_paused', PID_DIR, $tname);
}

function pid_started_task($tname) {
    @$pid = file_get_contents(pid_file_by_task_name($tname));
    return $pid;
}

function task_is_paused($tname)
{
    return file_exists(pause_file_by_task_name($tname));
}


function main($argv)
{
    if (!isset($argv[1])) {
        print_help();
        return -EINVAL;
    }

    $cmd = $argv[1];
    switch ($cmd) {
    case 'start':
        if (!isset($argv[2])) {
            foreach (periodically_list() as $task) {
                $cmd = sprintf("./task.sh %s %s %d",
                               pid_file_by_task_name($task->name()), $task->name(),
                               $task->interval());
                run_cmd($cmd, true, '', false);
                sleep(1);
                $pid = pid_started_task($task->name());
                pnotice("Task %s/%d started\n", $task->name(), $pid);
            }
            return 0;
        }

        $tname = $argv[2];
        $task = task_by_name($tname);
        if (!$task) {
            pnotice("Task '%s' is not registred\n", $tname);
            return -EINVAL;
        }

        $cmd = sprintf("./task.sh %s %s %d", pid_file_by_task_name($tname),
                       $tname, $task->interval());
        run_cmd($cmd, true, '', false);
        sleep(1);
        $pid = pid_started_task($tname);
        pnotice("Task %s/%d started\n", $tname, $pid);
        return 0;

    case 'stop':
        if (!isset($argv[2])) {
            foreach (periodically_list() as $task) {
                $pid = pid_started_task($task->name());
                if (!$pid) {
                    pnotice("Task '%s' has already been stopped\n", $task->name());
                    continue;
                }

                stop_daemon(pid_file_by_task_name($task->name()));
                pnotice("Task %s/%d stopped\n", $task->name(), $pid);
            }
            return 0;
        }

        $tname = $argv[2];
        $task = task_by_name($tname);
        if (!$task) {
            pnotice("Task '%s' has not registred\n", $tname);
            return -EINVAL;
        }

        $pid = pid_started_task($tname);
        if (!$pid) {
            pnotice("Task '%s' has not started\n", $tname);
            return -EINVAL;
        }

        stop_daemon(pid_file_by_task_name($tname));
        pnotice("Task %s/%d stopped\n", $tname, $pid);
        return 0;

    case 'pause':
        if (!isset($argv[2])) {
            foreach (periodically_list() as $task) {
                $pid = pid_started_task($task->name());
                if (!$pid) {
                    pnotice("Task '%s' not started\n", $task->name());
                    continue;
                }
                file_put_contents(pause_file_by_task_name($task->name()), '');
                pnotice("Task %s/%d paused\n", $task->name(), $pid);
            }
            return 0;
        }

        $tname = $argv[2];
        $task = task_by_name($tname);
        if (!$task) {
            pnotice("Task '%s' has not registred\n", $tname);
            return -EINVAL;
        }

        $pid = pid_started_task($tname);
        if (!$pid) {
            pnotice("Task '%s' has not started\n", $tname);
            return 0;
        }
        file_put_contents(pause_file_by_task_name($tname), '');
        pnotice("Task %s/%d paused\n", $task->name(), $pid);
        return 0;

    case 'resume':
        if (!isset($argv[2])) {
            foreach (periodically_list() as $task) {
                $pid = pid_started_task($task->name());
                if (!$pid) {
                    pnotice("Task %s has not started\n", $task->name());
                    continue;
                }
                unlink_safe(pause_file_by_task_name($task->name()));
                pnotice("Task %s/%d resumed\n", $task->name(), $pid);
            }
            return 0;
        }

        $tname = $argv[2];
        $task = task_by_name($tname);
        if (!$task) {
            pnotice("Task '%s' has not registred\n", $tname);
            return -EINVAL;
        }

        $pid = pid_started_task($tname);
        if (!$pid) {
            pnotice("Task '%s' has not started\n", $tname);
            return 0;
        }
        unlink_safe(pause_file_by_task_name($tname));
        pnotice("Task %s/%d resumed\n", $task->name(), $pid);
        return 0;

    case 'run':
        if (!isset($argv[2])) {
            pnotice("Task name is absent\n");
            return -EINVAL;
        }


        $tname = $argv[2];
        if (!task_by_name($tname)) {
            pnotice("Task '%s' has not found\n", $tname);
            return -EINVAL;
        }

        if (task_is_paused($tname)) {
            pnotice("task %s is paused\n", $tname);
            return 0;
        }

        run_task($tname);
        return 0;

    case 'stat':
        pnotice("Tasks list:\n");
        foreach (periodically_list() as $task) {
            $pid = pid_started_task($task->name());
            if (!$pid) {
                pnotice("\t%s: stopped\n", $task->name());
                continue;
            }
            pnotice("\t%s/%d: %s\n",
                   $task->name(), $pid,
                   task_is_paused($task->name()) ? "paused" : "running");
        }
        return 0;

    default:
        perror("Incorrect command\n");
        print_help();
        return -EINVAL;
    }

    return 0;
}

exit(main($argv));
