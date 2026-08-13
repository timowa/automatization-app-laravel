Task:
Рефакторинг системы логирования

Status:
BLOCKED

Notes:
Задача отмечена выполненной в backend-report.md, но проблема не решена.
Образ не пересобран (образ от 2026-08-05, изменения от 2026-08-13).
entrypoint.sh отсутствует в контейнере. chown/chmod не работают на NTFS.
См. задачу-преемник: 2026-08-13-log-permission-denied

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
Три проблемы: (1) плоская структура логов, (2) дублирование ошибок по каналам через LOG_STACK, (3) Permission denied в Docker.
Причина дублирования: LOG_STACK=single,daily,vk,vkRepost,vk-sync,check_tokens,stats,job,debug — необработанные исключения пишутся во все каналы.
Причина permission denied: файлы логов на хосте принадлежат timowa (644), www-data в контейнере не может писать.
Код приложения (Log::channel() вызовы) не меняется — только конфигурация и Docker.