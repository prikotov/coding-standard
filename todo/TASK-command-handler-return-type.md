---
name: CommandHandler return type — compute-проекты без БД
type: task
description: Все CommandHandler в task-orchestrator возвращают DTO — системная особенность compute-проектов без БД
created: 2026-05-06
priority: P3
status: closed
---

# CommandHandler return type — compute-проекты без БД

## Итог

Конвенция `command-handler.md` **остаётся без изменений**. Возврат `void`, ID или `IdDto` — правильное правило для CRUD+БД проектов.

В task-orchestrator все 3 CommandHandler возвращают DTO — это не исключение, а системная особенность compute-проекта без БД.

Решение: `phpcs:ignore` в коде с комментарием.

## Анализ

### Строгий CQRS

CQRS допускает только Command и Query. Третьего нет:
- Command — side effects, без возврата данных
- Query — данные, без side effects

`OrchestrateChain` делает и то, и другое — не вписывается в CQRS.

### Вариант: Store + Command/Query

Распилить на Command (запуск → token) + Query (чтение по token → DTO):
- Плюсы: replay/resume, history, monitoring, cancellation
- Минусы: overengineering для синхронного CLI-инструмента
- Имеет смысл при переходе к асинхронной модели (web API, очереди)

### Почему не меняем конвенцию

- Конвенция описывает CRUD+БД паттерн — она правильная
- 3 нарушения в одном проекте = особенность проекта, не дыра в конвенции
- Если добавим «разрешить DTO всегда» — потеряем чёткость CQRS для основных проектов (TasK)

### Когда пересмотреть

Если в task-orchestrаторе появится web API с асинхронным запуском цепочек — перейти на Store + Command/Query.
