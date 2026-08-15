#!/bin/bash

PROJECT="/home/r/rabota19vk/24it.biz"
PHP="/usr/local/php/cgi/8.5/bin/php"

if ! /usr/bin/pgrep -f "$PROJECT/artisan queue:work database" > /dev/null; then
    cd "$PROJECT" || exit 1

    nohup "$PHP" artisan queue:work database \
        --sleep=3 \
        --tries=3 \
        --timeout=90 \
        >> "$PROJECT/storage/logs/worker.log" 2>&1 &
fi
