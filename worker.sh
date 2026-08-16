#!/bin/bash

PROJECT="/home/r/rabota19vk/24it.biz"
PHP="/usr/local/php/cgi/8.5/bin/php"
LOG="$PROJECT/storage/logs/worker.log"

cd "$PROJECT" || exit 1

if /usr/bin/pgrep -f "$PHP artisan queue:work database" > /dev/null; then
    exit 0
fi

echo "Worker starting: $(date)" >> "$LOG"

exec "$PHP" artisan queue:work database \
    --sleep=3 \
    --tries=3 \
    --timeout=90 \
    >> "$LOG" 2>&1
