#!/bin/bash

PROJECT="/home/r/rabota19vk/24it.biz"
PHP="/usr/local/php/cgi/8.5/bin/php"
LOG="$PROJECT/storage/logs/worker.log"

if ! /usr/bin/pgrep -f "$PROJECT/artisan queue:work" > /dev/null; then
    cd "$PROJECT" || exit 1

    nohup "$PHP" artisan queue:work database \
        --sleep=3 \
        --tries=3 \
        --timeout=90 \
        >> "$LOG" 2>&1 &
fi
