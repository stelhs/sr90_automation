#!/bin/bash

cd "$(dirname "$0")"
while [ 1 ]
do
	for file in periodically/*; do ./$file auto; done
	sleep 1;
done
