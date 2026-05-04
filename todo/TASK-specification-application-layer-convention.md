---
type: feat
created: 2026-05-04
priority: P2
status: todo
---

# Specification: устранить противоречие в конвенциях + накрыть Deptrac

## Проблема

В конвенциях есть противоречие между двумя документами:

- **`layers/domain/specification.md`** говорит:
  > «Спецификации используются **только в слое Domain**.»

- **`layers/application.md`** в разделе «Application → Domain» говорит:
  > «**Спецификации**: через интерфейсы из `Domain\Specification\*`»

Application.md разрешает Application дёргать Specification напрямую, а specification.md запрещает.

## Как должно быть

Specification — деталь реализации Domain. Application не должен вызывать `isSatisfiedBy()` напрямую.

Если Application нужно бизнес-правило, инкапсулированное в Specification:
- Application идёт через **Domain Service**, который внутри использует Specification.
- Application не знает, какие спецификации комбинируются и существуют ли они вообще.

Это согласуется с принципом «Application не содержит бизнес-логику, только оркестрацию».

## Что сделать

### 1. Уточнить `specification.md`

- Явно прописать: «Application слой не вызывает Specification напрямую. Бизнес-правила, реализованные через Specification, доступны Application через Domain Service».
- Добавить пример: Domain Service инкапсулирует Specification, Application дёргает Domain Service.

### 2. Исправить `application.md`

- В таблице «Application → Domain» заменить строку:
  - ~~«Спецификации: через интерфейсы из `Domain\Specification\*`»~~
  - На: «Спецификации: только через Domain Service. Прямой вызов Specification из Application запрещён».

### 3. Накрыть Deptrac

- В `config/deptrac/depfile.yaml` добавить правило: Application не может зависеть от `Domain\Specification\*` напрямую.
- Specification должна быть видима только внутри Domain (Domain Service, Entity, другой Specification).

## Зависимости

- Deptrac-конфиг: `config/deptrac/depfile.yaml`
- Конвенции: `docs/conventions/layers/domain/specification.md`, `docs/conventions/layers/application.md`
