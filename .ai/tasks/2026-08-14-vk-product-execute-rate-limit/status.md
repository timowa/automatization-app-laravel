Task:
Рефакторинг CreateVkProductJob — execute и rate limit

Status:
PLANNED

Created:
2026-08-14

Current phase:
ANALYSIS

Backend:
TODO

Frontend:
N/A

Review:
TODO

Blockers:

Нет. Есть вопросы по замене устаревших методов фото (см. analysis.md → Questions).

Notes:

Логи: storage/logs/vk/posts/error-2026-08-14.log
Зафиксировано: "Too many requests per second", "Unknown method passed"
Затронуты 3 job: CreateVkProductJob, EditVkProductJob, ArchiveVkProductJob
Документация VK API: docs/vk-api/ (execute.md, market.md, photos.md)