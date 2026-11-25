# Changelog

История релизов до v0.35.0 — в [GitHub Releases](https://github.com/prikotov/coding-standard/releases).

## v0.35.0 — 2026-11-25

[Сравнение v0.34.2...v0.35.0](https://github.com/prikotov/coding-standard/compare/v0.34.2...v0.35.0)

Изоляция Embeddable VO слоем `DomainEmbeddable` и замкнутый каталог типов Domain в Deptrac; приватные свойства VO и классификация их типов по namespace в PHPCS.

Несовместимое изменение (версия 0.x): проверки VO и `make deptrac` в проектах-потребителях начнут сообщать ранее скрытые нарушения.
