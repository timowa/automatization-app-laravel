# VK API Documentation

Документация по методам VK API, используемым в проекте vk19-app.

Методы сгруппированы по разделам VK API. Каждый файл содержит описание методов,
параметры, результаты выполнения и коды ошибок.

Источник: https://dev.vk.com/ru/method

## Используемые группы методов

| Файл | Группа | Методы | Jobs проекта |
|------|--------|--------|--------------|
| [wall.md](wall.md) | Wall | wall.post, wall.repost, wall.getById, wall.createComment | CreateVkPostJob, CreateVkRepostJob, CreateVkCommentJob |
| [stories.md](stories.md) | Stories | stories.getPhotoUploadServer, stories.save | CreateVkStoriesJob, CreateVkLoopStoryJob |
| [photos.md](photos.md) | Photos | photos.getWallUploadServer, photos.saveWallPhoto | CreateVkPostJob, CreateVkProductJob |
| [market.md](market.md) | Market | market.add, market.edit, market.delete | CreateVkProductJob, EditVkProductJob, ArchiveVkProductJob |
| [groups.md](groups.md) | Groups | groups.get | CreateVkRepostJob |
| [execute.md](execute.md) | Execute | execute (VKScript) | CreateVkRepostJob, ArchiveVkProductJob |

## Связь методов с Jobs

| Job | VK API методы |
|-----|---------------|
| CreateVkPostJob | wall.post, photos.getWallUploadServer, photos.saveWallPhoto |
| CreateVkRepostJob | groups.get, wall.repost (через execute) |
| CreateVkStoriesJob | stories.getPhotoUploadServer, stories.save |
| CreateVkLoopStoryJob | stories.getPhotoUploadServer, stories.save |
| EndVkLoopStoryJob | (нет VK API вызовов, только БД) |
| CreateVkCommentJob | wall.createComment |
| CreateVkProductJob | photos.getMarketUploadServer*, photos.saveMarketPhoto*, market.add |
| EditVkProductJob | market.edit |
| ArchiveVkProductJob | market.delete (через execute) |

> *Методы photos.getMarketUploadServer и photos.saveMarketPhoto удалены из актуальной
> документации VK API. См. примечание в [photos.md](photos.md).