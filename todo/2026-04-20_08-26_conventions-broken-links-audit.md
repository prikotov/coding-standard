# Аудит битых ссылок в конвенциях (docs/conventions/)

**Роль:** Бэкендер (Левша)
**Дата:** 2026-04-20
**Объект:** `docs/conventions/` — все `.md` файлы, внутренние markdown-ссылки
**Задача:** [TASK-fix-conventions-broken-links](../../todo/TASK-fix-conventions-broken-links.todo.md)

---

## Суть

Конвенции перенесены из upstream-проекта. Относительные пути и имена файлов не обновлены. Обнаружено **50 битых внутренних ссылок** в **15 файлах**.

Конвенции приходят извне — правки в файлы не вносятся. Данный отчёт передаётся upstream для исправления. По каждой проблеме проходим отдельно.

---

## Методика

Автоматический поиск всех `[text](link)` в `.md` файлах `docs/conventions/`, разрешение относительных путей, проверка существования целевого файла. Внешние ссылки (`http*`) и якоря (`#...`) исключены.

---

## Раздел 1. Неправильные имена файлов (10 ссылок)

**Суть:** Файл существует, но ссылка указывает на старое/неверное имя.

- [x] `layers/application/command_handler.md:3` — `[Use Case](use_cases.md)` — множественное число вместо единственного → заменить на `use_case.md`
- [x] `layers/application/command_handler.md:12` — `[Application](application_layer.md)` — старое имя файла → заменить на `../application.md`
- [x] `layers/application/query_handler.md:3` — `[Use Case](use_cases.md)` — множественное число → заменить на `use_case.md`
- [x] `layers/application/query_handler.md:10` — `[Application](application_layer.md)` — старое имя файла → заменить на `../application.md`
- [x] `layers/application/use_case.md:11` — `[Application](application_layer.md)` — старое имя файла → заменить на `../application.md`
- [x] `layers/domain/repository.md:29` — `[criteria.md](../infrastructure/criteria.md)` — файл называется `criteria-mapper.md` → заменить на `../infrastructure/criteria-mapper.md`
- [x] `layers/integration/listener.md:3` — `[событие](../application/events.md)` — файл называется `event.md` → заменить на `../application/event.md`
- [x] `layers/integration/listener.md:9` — `[Use Case](../application/use_cases.md)` — множественное число → заменить на `../application/use_case.md`
- [x] `layers/integration/listener.md:18` — `[Use Case](../application/use_cases.md)` — дубликат → заменить на `../application/use_case.md`
- [x] `layers/integration/listener.md:26` — `[Integration](integration.md)` — путь без `../` → заменить на `../integration.md`

---

## Раздел 2. Неправильные относительные пути (4 ссылки)

**Суть:** Файл существует в `docs/conventions/`, но относительный путь от исходного файла указывает мимо.

- [x] `layers/application.md:45` — `[Use Cases](use_case.md)` — файл в `layers/application/use_case.md` → заменить на `application/use_case.md`
- [x] `layers/application.md:64` — `[Command и CommandHandler](command_handler.md)` — аналогично → заменить на `application/command_handler.md`
- [x] `layers/application.md:82` — `[Query и Query Handler](query_handler.md)` — аналогично → заменить на `application/query_handler.md`
- [x] `principles/values.md:8` — `[Code Conventions](README.md)` — файл `principles/README.md` не существует; вероятно, ссылка на `index.md` или `code_style.md` → заменить на `../index.md`

---

## Раздел 3. Ссылки на несуществующие документы (9 ссылок)

**Суть:** Целевые документы не существуют ни в проекте, ни в `docs/conventions/`. upstream нужно либо создать документы, либо удалить ссылки.

### 3.1. `guides/dto.md` → `core_patterns/dto.md` (4 ссылки)

- [x] `layers/application.md:100` — `[DTO](../guides/dto.md)` → `[DTO](../core_patterns/dto.md)`
- [x] `layers/application/command_handler.md:23` — `[DTO](../../guides/dto.md)` → `[DTO](../../core_patterns/dto.md)`
- [x] `layers/application/query_handler.md:20` — `[DTO](../../guides/dto.md)` → `[DTO](../../core_patterns/dto.md)`
- [x] `layers/application/query_handler.md:21` — `[DTO](../../guides/dto.md)` → `[DTO](../../core_patterns/dto.md)`

### 3.2. `guides/enum.md` → `core_patterns/enum.md` (1 ссылка)

- [x] `layers/application/query_handler.md:21` — `[Enum](../../guides/enum.md)` → `[Enum](../../core_patterns/enum.md)`

### 3.3. `architecture/events/transactions.md` — создан документ (2 ссылки)

- [x] `layers/application/command_handler.md:34` — `[Events & Transactions...](../../../architecture/events/transactions.md)` → документ создан, ссылка восстановлена
- [x] `layers/application/event.md:18` — `[Events & Transactions...](../../../architecture/events/transactions.md)` → документ создан, ссылка восстановлена

### 3.4. `modules/index.md` → `modules/configuration.md` (1 ссылка)

- [x] `index.md:33` — `[Модули](modules/index.md)` → `[Модули](modules/configuration.md)`

### 3.5. `layers/theme/README.md` → внешний URL Bootstrap (1 ссылка)

- [x] `layers/presentation/view.md:3` — `[Bootstrap 5 Phoenix](../theme/README.md)` → внешняя ссылка на Bootstrap docs

---

## Раздел 4. Ссылки на файлы другого проекта (22 ссылки)

**Суть:** Конвенции перенесены из другого проекта. Ссылки указывают на файлы, которых в task-orchestrator нет и не планируется.

### 4.1. `src/DataFixtures/*` — DataFixtures не используются (5 ссылок)

- [ ] `testing/index.md:750` — `[UserFixtures.php](../../src/DataFixtures/UserFixtures.php)`
- [ ] `testing/index.md:751` — `[UserRoleFixture.php](../../src/DataFixtures/UserRoleFixture.php)`
- [ ] `testing/index.md:752` — `[ProjectFixtures.php](../../src/DataFixtures/ProjectFixtures.php)`
- [ ] `testing/index.md:753` — `[TeamFixtures.php](../../src/DataFixtures/TeamFixtures.php)`
- [ ] `testing/index.md:754` — `[UserAccessTokenFixture.php](../../src/DataFixtures/UserAccessTokenFixture.php)`

### 4.2. `src/Kernel.php` — структура проекта отличается (3 ссылки)

- [ ] `symfony-applications.md:8` — `[Common\Kernel](src/Kernel.php)`
- [ ] `symfony-applications.md:100` — `[Common\Kernel](src/Kernel.php)`
- [ ] `symfony-folder-structure.md:8` — `[Common\Kernel](src/Kernel.php)`

### 4.3. `src/Module/Health/...` — пример из другого проекта (1 ссылка)

- [ ] `layers/domain/repository.md:254` — `[InMemoryServiceStatusRepository.php](../../../src/Module/Health/Infrastructure/Repository/ServiceStatus/InMemoryServiceStatusRepository.php)`

### 4.4. Конфигурационные файлы, отсутствующие в проекте (8 ссылок)

- [ ] `testing/index.md:858` — `[phpcs.xml.dist](../../phpcs.xml.dist)` — файл отсутствует
- [ ] `testing/index.md:1109` — `[phpcs.xml.dist](../../phpcs.xml.dist)` — дубликат
- [ ] `ops/phpmd-suppressions-guidelines.md:30` — `[phpmd.xml](../../../phpmd.xml)` — файл отсутствует
- [ ] `testing/index.md:1110` — `[phpmd.xml](../../phpmd.xml)` — дубликат
- [ ] `ops/phpmd-suppressions-guidelines.md:31` — `[phpmd.baseline.xml](../../../phpmd.baseline.xml)` — файл отсутствует
- [ ] `ops/phpmd-suppressions-guidelines.md:179` — `[ExcessiveMethodLength](../../../phpmd.xml)` — из `criteria-mapper.md` (в задаче указано как `criteria-mapper.md:179`, уточнить)

> **Уточнение:** ссылка `phpmd.xml` из `layers/infrastructure/criteria-mapper.md:179` — это тоже ссылка на отсутствующий файл, относится к той же категории.

### 4.5. `Makefile` — отсутствует (3 ссылки)

- [ ] `ops/phpmd-suppressions-guidelines.md:32` — `[Makefile](../../../Makefile)`
- [ ] `testing/index.md:1111` — `[Makefile](../../Makefile)`
- [ ] `testing/index.md:1126` — `[Makefile](../../Makefile)`

### 4.6. `depfile.yaml` — отсутствует (2 ссылки)

- [ ] `testing/index.md:846` — `[depfile.yaml](../../depfile.yaml)`
- [ ] `testing/index.md:1112` — `[depfile.yaml](../../depfile.yaml)`

### 4.7. `tests/AGENTS.md` — отсутствует (1 ссылка)

- [ ] `testing/index.md:1124` — `[tests/AGENTS.md](../../tests/AGENTS.md)`

---

## Раздел 5. Существующие файлы с неверным относительным путём (5 ссылок)

**Суть:** Целевой файл существует в корне проекта, но относительный путь из `docs/conventions/testing/` указывает неверно (не хватает одного `../`).

- [ ] `testing/index.md:776` — `[phpunit.xml.dist](../../phpunit.xml.dist)` → исправить на `../../../phpunit.xml.dist`
- [ ] `testing/index.md:1107` — `[phpunit.xml.dist](../../phpunit.xml.dist)` → исправить на `../../../phpunit.xml.dist`
- [ ] `testing/index.md:829` — `[psalm.xml](../../psalm.xml)` → исправить на `../../../psalm.xml`
- [ ] `testing/index.md:1108` — `[psalm.xml](../../psalm.xml)` → исправить на `../../../psalm.xml`
- [ ] `testing/index.md:889` — `[AGENTS.md](../../AGENTS.md)` → исправить на `../../../AGENTS.md`
- [ ] `testing/index.md:1125` — `[AGENTS.md](../../AGENTS.md)` → исправить на `../../../AGENTS.md`

> **Уточнение:** `AGENTS.md` в корне существует, дубликат на строке 1125 — итого 6 ссылок в этом разделе (5 уникальных файлов-источников, но 6 вхождений).

---

## Сводная таблица

| # | Раздел | Ссылок | Сложность исправления |
|---|--------|--------|-----------------------|
| 1 | Неправильные имена файлов | 10 | 🟢 Легко — заменить имя |
| 2 | Неправильные относительные пути | 4 | 🟢 Легко — исправить путь |
| 3 | Несуществующие документы | 9 | 🟡 Средне — создать upstream или удалить |
| 4 | Файлы другого проекта | 22 | 🟢 Легко — удалить ссылки |
| 5 | Существующие файлы, неверный путь | 6 | 🟢 Легко — исправить путь |
| | **Итого** | **51** | |

---

## Рекомендации для upstream

1. **Быстрые победы (разделы 1, 2, 5):** 20 ссылок исправляются механической заменой — можно批量 исправить скриптом.
2. **Удаление нерелевантных ссылок (раздел 4):** 22 ссылки на файлы, которых нет в типовом проекте — рекомендуется вынести в «примеры» или сделать опциональными.
3. **Создание недостающих документов (раздел 3):** 9 ссылок требуют создания `guides/dto.md`, `guides/enum.md`, `architecture/events/transactions.md` и др., либо удаления ссылок на них.

---

*Отчёт сформирован автоматически скриптом проверки ссылок с ручной классификацией проблем.*
