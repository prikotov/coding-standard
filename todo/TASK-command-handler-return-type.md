---
name: CommandHandler return type — расширить для no-DB контекста
type: task
description: Конвенция CommandHandler return type описывает только CRUD+БД сценарий. Нужно учесть no-DB (compute) сценарии.
---

# Задача: CommandHandler return type — no-DB контекст

## Проблема

Конвенция `command-handler.md` разрешает CommandHandler возвращать:
- `void`
- Идентификатор (int, Uuid)
- `IdDto` (если нужно вернуть несколько ID)

Это описывает CRUD+БД паттерн. Но существуют сценарии без БД:

**Пример:** `OrchestrateChainCommandHandler` запускает цепочку AI-агентов, собирает результаты в памяти и возвращает `OrchestrateChainResultDto` с метриками и exit code. Нет БД, нет сущности, ID не применим.

## Что нужно проработать

1. Расширить конвенцию `command-handler.md` — описать допустимые типы возврата для no-DB сценария.
2. Обновить `CommandHandlerReturnTypeSniff` — разрешить DTO в определённых случаях (или по suppress).
3. Определить критерий: как отличить «compute» UseCase от «CRUD» UseCase? Возможные варианты:
   - По suppress-комментарию (current approach).
   - По отсутствию `PersistenceManagerInterface` в зависимостях.
   - По отдельному интерфейсу/атрибуту.

## Текущий workaround

`OrchestrateChainCommandHandler` использует `phpcs:ignore` с ссылкой на эту задачу.

## Связанные файлы

- `docs/conventions/layers/application/command-handler.md`
- `src/Sniffs/Application/CommandHandlerReturnTypeSniff.php`
- Проект task-orchestrator: `OrchestrateChainCommandHandler.php`
