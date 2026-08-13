Task:
Создать отсутствующий базовый класс App\Scenarios\StatusRule

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
Обнаружено при написании технической документации (docs/scenarios.md).
6 статус-правил наследуются от несуществующего класса App\Scenarios\StatusRule.
Без него 9 из 10 сценариев падают с Fatal Error при инстанцировании.