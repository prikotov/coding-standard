---
name: Authorization
type: rule
description: Правила проверки прав презентационного слоя
---

# Проверка прав презентационного слоя (Presentation Authorization)

## Определение

**Проверка прав презентационного слоя (Presentation Authorization)** — способ ограничить доступ к публичным
интерфейсам приложения через Permission Enum, Action Enum, Rule, Voter и Grant. Используем встроенную модель
Symfony Security, см. [Security & Authorization](https://symfony.com/doc/current/security.html).

## Архитектура авторизации

```
Controller / Template / UI
       │
       ▼
      Grant ───► AuthorizationChecker ───► Voter ───► Rule ───► PermissionEnum
         ▲                                     │          │
         └──────────── ActionEnum + subject ───┴──────────┘
```

**Компоненты:**

| Компонент | Назначение | Документация |
|-----------|------------|--------------|
| **PermissionEnum** | Роли `ROLE_*` для модуля | [permission-enum.md](permission-enum.md) |
| **ActionEnum** | Атрибуты действий для `isGranted()` | [action-enum.md](action-enum.md) |
| **Rule** | Итоговая логика доступа | [rule.md](rule.md) |
| **Voter** | Symfony-адаптер, делегирующий в Rule | [voter.md](voter.md) |
| **Grant** | Фасад пользовательского интерфейса (UI) над AuthorizationChecker | [grant.md](grant.md) |

## Общие правила

- Каждый модуль определяет собственный `PermissionEnum` с именами ролей `ROLE_*`.
- `ActionEnum` описывает действия: `view`, `edit`, `delete`.
- `Rule` содержит итоговую логику доступа.
- `Voter` принимает решение Symfony Security и делегирует в Rule.
- `Grant` только вызывает `AuthorizationCheckerInterface::isGranted()` для UI.
- Контроллеры и шаблоны не вызывают Rule напрямую.
- Domain/Infrastructure не используем внутри Rule/Voter/Grant.

## Матрица Action-Permission

ActionEnum и PermissionEnum — независимые enum'ы. Связь между ними реализует [Rule](rule.md):

```
ActionEnum          Rule                 PermissionEnum
─────────          ────                 ──────────────
view         ───►  canView()    ───►    viewOwn / viewAll
edit         ───►  canEdit()    ───►    editOwn / editAll
delete       ───►  canDelete()  ───►    deleteOwn / deleteAll
```

PermissionEnum добавляется в `security.yaml` и назначается ролям пользователей.

## Зависимости

- **Rule:** `TokenInterface`, `RoleHierarchyInterface`, DTO Presentation, публичный `QueryBus` для фактов доступа.
- **Voter:** Rule, `TokenInterface`, `ActionEnum`, subject Presentation.
- **Grant:** `AuthorizationCheckerInterface`, `ActionEnum`, subject Presentation.
- **Запрещено:** сервисы Domain, ORM-репозитории, Entity Manager, глобальные синглтоны.

## Расположение

```
apps/<app>/src/Module/<ModuleName>/Security/<SubjectName>/
├── PermissionEnum.php   # Роли модуля
├── ActionEnum.php       # Атрибуты для isGranted (view, edit, delete...)
├── Rule.php             # Логика проверки
├── Voter.php            # Голосующий объект
└── Grant.php            # Обёртка для контроллеров/шаблонов
```

## Как используем

1. Определяем [Permission Enum](permission-enum.md) и добавляем значения в `security.yaml`.
2. Определяем [Action Enum](action-enum.md) с атрибутами действий (view, edit, delete...).
3. Реализуем [Rule](rule.md) для проверки прав.
4. Создаём [Voter](voter.md), который делегирует проверку в Rule.
5. При необходимости создаём [Grant](grant.md) для UI-проверок.
6. Точку входа (endpoint) защищаем через `$this->isGranted(ActionEnum::view->value, $subject)`.

## Чек-лист для проведения ревью кода

- [ ] Permission Enum лежит в каталоге Security и содержит только значения `ROLE_*`.
- [ ] Action Enum содержит атрибуты действий (view, edit, delete...).
- [ ] Rule содержит итоговую логику доступа.
- [ ] Voter делегирует проверку в Rule и зарегистрирован как сервис.
- [ ] Значения Permission Enum добавлены в `security.yaml`.
- [ ] Grant объявлен `final readonly`, не дублирует Rule и не содержит бизнес-логики.
