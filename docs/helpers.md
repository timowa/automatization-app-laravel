# Вспомогательные классы и функции

## Назначение

Классы и функции, обеспечивающие инфраструктуру проекта: парсинг, разрешение зависимостей, резолверы, форматтеры.

## Helper-классы (app/Helpers/)

### OfferParser

**Файл:** `app/Helpers/OfferParser.php`

Парсит сырой массив вебхука в `OfferData` DTO. Ищет агента по телефону, преобразует строковые enum-значения.

Подробнее: `docs/webhook-and-offer.md`.

### OfferChangesDetector

**Файл:** `app/Helpers/OfferChangesDetector.php`

Сравнивает предыдущий и новый Offer, возвращает массив изменённых полей.

Подробнее: `docs/webhook-and-offer.md`.

### JobResolver

**Файл:** `app/Helpers/JobResolver.php`

Маппинг `PublicationTaskType` → класс Job. Возвращает FQCN Job по типу задачи.

Подробнее: `docs/jobs.md`.

### PublicationTaskDependenceInspector

**Файл:** `app/Helpers/PublicationTaskDependenceInspector.php`

Определяет тип родительской задачи (от какой зависит) для данного типа задачи.

Подробнее: `docs/publications-and-tasks.md`.

### PublicationTaskDependencyResolver

**Файл:** `app/Helpers/PublicationTaskDependencyResolver.php`

Переводит зависимые задачи из WAITING в PENDING после завершения родительской.

Подробнее: `docs/publications-and-tasks.md`.

### ScenarioVkPostTemplateResolver

**Файл:** `app/Helpers/ScenarioVkPostTemplateResolver.php`

Маппинг `ScenarioType` → класс шаблона поста.

Подробнее: `docs/vk-wallpost-templates.md`.

## Глобальные функции (app/helpers.php)

**Файл:** `app/helpers.php`

### viewJson(bool $success, ?array $messages, ?string $redirect, array $extra): never

Отправляет JSON-ответ для AJAX-запросов админ-панели и завершает выполнение.

### downloadFile(string $url, string $directory): string

Скачивает файл по URL через cURL в указанную директорию. Возвращает путь к локальному файлу. Используется для загрузки фото перед отправкой в VK API.

### isImageUrl(string $url): bool

Проверяет, что URL указывает на изображение (HEAD-запрос через cURL, проверка Content-Type).

### formatPrice(int $price): string

Форматирование цены с пробелами-разделителями: `number_format($price, 0, '', ' ')`.

### formatArea(float $area): string

Форматирование площади: `number_format($area, 1, ',', ' ')` (1 знак после запятой, запятая).

### phoneFormat($phone)

Форматирование номера телефона по маске. Поддерживает форматы 7, 10, 11 цифр.

### array_keys_from_column($array, $column)

Индексирует массив массивов по значению колонки. Возвращает false при дублировании ключей.

### appLogger(string $channel = 'job')

Возвращает `Log::channel($channel)`. Обёртка для удобства.

## Трейты

### EnumHasLabel

**Файл:** `app/Traits/EnumHasLabel.php`

`tryFromLabel(string $label): ?self` — обратный поиск enum по label.

Подробнее: `docs/enums.md`.

## Исключения

### NotFoundException

**Файл:** `app/Exceptions/NotFoundException.php`

Общее исключение "не найдено". Используется в Job'ах при отсутствии оффера, агента, токена, поста.

### OfferParserException

**Файл:** `app/Exceptions/OfferParserException.php`

Исключение при ошибке парсинга оффера (нет агента, нет телефона, нет кода).