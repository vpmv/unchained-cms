#!/bin/bash

env=$1
phar=$(pwd)"/composer.phar"
base_cmd="$phar install --prefer-dist --no-progress --optimize-autoloader --no-interaction --no-scripts"
echo $phar

if [ ! -f "$phar" ]; then
    echo "Composer package not found!"
    exit 1
fi

if [ "$env" == "dev" ]; then
    eval "$base_cmd"
else
    eval "$base_cmd --no-dev"
fi

#yarn install

exit 0