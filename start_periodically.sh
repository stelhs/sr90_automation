#!/bin/bash

while [ 1 ]
do
	for file in periodically/*; do ./$file; done
	sleep 1;
done
