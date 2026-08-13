Task:
CreateVkProductJob — некорректный статус SUCCESS при ошибках создания товаров

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

Нет.

Notes:

Файл: app/Jobs/CreateVkProductJob.php, строка 143 — безусловный SUCCESS.
Изменение минимальное: счётчик + throw RuntimeException при 0 товаров.
RuntimeException ловится существующим catch (\Throwable) на строке 164.