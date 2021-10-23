#!/bin/bash

cd "$(dirname "$0")"
echo $$ > $1
echo "$3"
./io.php sequence $2 $3
rm $1
