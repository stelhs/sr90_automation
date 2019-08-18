#!/bin/bash

cd "$(dirname "$0")"
while [ 1 ]
do
	if [ ! -e periodically/STOP ]
	then
		for file in periodically/*; do
			./$file auto; 
		done
	fi
	sleep 1;
done
