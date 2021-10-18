#!/bin/bash

cd "$(dirname "$0")"
echo $$ > $1
while [ 1 ]
do
    ./periodically.php run $2
        sleep $3;
done

