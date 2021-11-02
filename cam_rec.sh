#!/bin/bash
# $1 - PID file
# $2 - Started file indacator
# $3 - command

cd "$(dirname "$0")"
while [ 1 ]
do
    $3
    rm $1

    if [ ! -f $2 ]; then
        break;
    fi
done
