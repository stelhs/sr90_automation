#!/bin/bash
# $1 - sound file name or 'stop' key word
# $2 - volume in precent (1-100)

function start {
    ./io.php relay_set sbio1 1 1
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


start
amixer -q set Master 100%
amixer -q set 'Speaker Boost' 100%
amixer -q set PCM unmute
amixer -q set Master unmute
amixer -q set 'Speaker Boost' unmute
amixer -q set PCM $VOLUME%
aplay $1
stop
