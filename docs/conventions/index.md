---
name: Table of Contents
type: index
description: Индекс всех конвенций проекта
---

# Содержание

## Принципы и Стандарты
- [Ценности](principles/values.md)
- [Стиль кода (Code Style)](principles/code-style.md)
## Базовые паттерны (Core Patterns)
- [Компонент (Component)](core-patterns/component.md)
- [Список (List)](core-patterns/list.md)
- [Перечисление (Enum)](core-patterns/enum.md)
- [Исключение (Exception)](core-patterns/exception.md)
- [Внешний сервис (External Service)](core-patterns/external-service.md)
- [Враппер (Wrapper)](core-patterns/wrapper.md)
- [Фабрика (Factory)](core-patterns/factory.md)
- [Хелпер (Helper)](core-patterns/helper.md)
- [Маппер (Mapper)](core-patterns/mapper.md)
- [Map-класс (Map)](core-patterns/map.md)
- [Сервис (Service)](core-patterns/service.md)
- [Объект передачи данных (DTO)](core-patterns/dto.md)
- [Трейт (Trait)](core-patterns/trait.md)
- [Объект-Значение (Value Object)](core-patterns/value-object.md)

## Тестирование
- [Testing](testing/index.md)

## Конфигурация
- [Конфигурация в Symfony](configuration/configuration.md)

## Модульная архитектура
- [Структура папок на Symfony](symfony-folder-structure.md)
- [Приложения на фреймворке Symfony](symfony-applications.md)
- [Модули](modules/index.md)
- [Конфигурирование модулей](modules/configuration.md)

## Операционные практики
- [Операционные практики](ops/index.md)

## Слои Архитектуры

- [Взаимодействие слоёв (Layer Interaction)](layers/layers.md)
- [Слой Приложения (Application)](layers/application.md)
    - [Сценарий использования (Use Case)](layers/application/use-case.md)
    - [Обработчик Команд (Command Handler)](layers/application/command-handler.md)
    - [Обработчик Запросов (Query Handler)](layers/application/query-handler.md)
    - [Событие (Event)](layers/application/event.md)
- [Архитектура](architecture/index.md)
    - [События и транзакции БД](architecture/events/transactions.md)
- [Слой Домена (Domain)](layers/domain.md)
    - [Сущность (Entity)](layers/domain/entity.md)
    - [Критерий (Criteria)](layers/domain/criteria.md)
    - [Репозиторий (Repository)](layers/domain/repository.md)
    - [Спецификация (Specification)](layers/domain/specification.md)
    - [Калькулятор (Calculator)](layers/domain/calculator.md)
- [Слой Инфраструктуры (Infrastructure)](layers/infrastructure.md)
    - [CriteriaMapper](layers/infrastructure/criteria-mapper.md)
    - [Репозиторий (Repository)](layers/infrastructure/repository.md)
- [Слой интеграций (Integration)](layers/integration.md)
    - [Слушатель Событий (Event Listener)](layers/integration/listener.md)
- [Слой Представления (Presentation)](layers/presentation.md)
    - [Слой Представления (Presentation Layer)](layers/presentation/presentation.md)
    - [Перечисление действий (Action Enum)](layers/presentation/action-enum.md)
    - [Авторизация (Authorization)](layers/presentation/authorization.md)
    - [Консольная команда (Console Command)](layers/presentation/console-command.md)
    - [Контроллер (Controller)](layers/presentation/controller.md)
    - [Формы (Forms)](layers/presentation/forms.md)
    - [Грант (Grant)](layers/presentation/grant.md)
    - [Контроллер списка (List Controller)](layers/presentation/list-controller.md)
    - [Перечисление разрешений (Permission Enum)](layers/presentation/permission-enum.md)
    - [Маршруты (Route)](layers/presentation/route.md)
    - [Twig-компонент (Twig Component)](layers/presentation/twig-component.md)
    - [Twig Extension](layers/presentation/twig-extension.md)
    - [Правило (Rule)](layers/presentation/rule.md)
    - [Ограничение частоты запросов (Rate Limiter)](layers/presentation/rate-limiter.md)
    - [Представление (View)](layers/presentation/view.md)
    - [Голосователь (Voter)](layers/presentation/voter.md)
