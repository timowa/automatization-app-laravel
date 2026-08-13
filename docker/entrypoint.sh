#!/bin/sh
set -e

# Создаём поддиректории для VK-логов (volume монтирует с хоста, директории из образа не сохраняются)
mkdir -p /var/www/html/storage/logs/vk/posts \
    /var/www/html/storage/logs/vk/reposts \
    /var/www/html/storage/logs/vk/sync \
    /var/www/html/storage/logs/vk/tokens \
    /var/www/html/storage/logs/vk/stats

# Запускаем php-fpm (master от root, workers от www-data)
exec php-fpm
