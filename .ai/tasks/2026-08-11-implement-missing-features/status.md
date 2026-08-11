Task:
Реализация нереализованных элементов проекта (сценарии, jobs, шаблоны, баги)

Status:
PLANNED

Created:
2026-08-11

Current phase:
ANALYSIS

Backend:
TODO

Frontend:
N/A

Review:
TODO

Blockers:
Нет

Notes:
Задача backend-only. 9 сценариев, 6 jobs, 4 шаблона, модель VkProduct, модель VkLoopStory, 3 бага.
is_active — легаси, убирается из парсера и модели (не баг, заменено полем status).
Sold vs Feedback — РЕШЕНО: Sold имеет доп. правило "первая запись по code со статусом архив", Feedback — последующие.
Loop-story scheduler — механика не описана в AGENTS.md детально, реализована по аналогии.
Предыдущий оффер ищется по code (последний по id), не по точному совпадению полей.