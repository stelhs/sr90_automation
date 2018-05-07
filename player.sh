#!/bin/bash
# $1 - sound file name or 'stop' key word
# $2 - volume in precent (1-100)
# $3 - duration in seconds

function start {
    ./io.php relay_set sbio1 1 1
    sleep(1)
}

function stop {
    ./io.php relay_set sbio1 1 0
}

if [ $1 == "stop" ]
then
    stop
    exit
fi


VOLUME=100
if [ -n "$2" ]
then
    VOLUME=$2
fi


DURATION=0
if [ -n "$3" ]
then
    DURATION=$3
fi


start
amixer -q set Master 100%
amixer -q set PCM unmute
amixer -q set Master unmute
amixer -q set PCM $VOLUME%
aplay --duration=$DURATION $1
stop
