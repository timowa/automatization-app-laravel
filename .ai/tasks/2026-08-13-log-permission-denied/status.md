Task:
Permission denied при записи логов — исправление после неудачного рефакторинга

Status:
PLANNED

Created:
2026-08-13

Current phase:
ANALYSIS

Backend:
READY

Frontend:
N/A

Review:
READY

Blockers:
Нет.

Notes:
Предыдущая задача (2026-08-13-logging-refactor) изменила конфигурацию и Docker-файлы, но:
1. Образ не пересобран — entrypoint.sh отсутствует в контейнере.
2. chown/chmod в entrypoint.sh не работают на NTFS-монтировании Windows.
3. Файлы логов принадлежат root (созданы через docker exec), www-data не может писать.
4. Избыточное монтирование ./storage/logs в docker-compose.yml.
5. VK-поддиректории (reposts, sync, tokens, stats) не созданы.

Решение: упростить entrypoint.sh (убрать chown/chmod, оставить mkdir), удалить избыточное монтирование, очистить старые логи, пересобрать образ. Artisan-команды запускать от www-data.