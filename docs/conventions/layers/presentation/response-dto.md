---
package: prikotov/coding-standard
name: Response DTO
type: rule
description: Правила создания Response DTO презентационного слоя
---

# Response DTO презентационного слоя (Presentation Response DTO)

## Определение

**Presentation Response DTO** — транспортная модель публичного ответа слоя Presentation, которую контроллер сериализует
в JSON, HTML-контекст или другой внешний контракт после вызова слоя Application.

## Общие правила

- Response DTO объявляем как `final readonly class`.
- DTO описывает только внешний контракт ответа и не содержит логики валидации.
- Разрешены метаданные, влияющие на сериализацию и документацию ответа:
  атрибуты `OpenAPI`, метаданные сериализатора, PHPDoc для коллекций.
- Response DTO не должен содержать `Assert`, пользовательских валидаторов, `Callback`, `validate*()` и логики в конструкторе.
- Нормализация ответа выполняется в Mapper/контроллере (mapper/controller) до создания DTO, а не внутри DTO.
- Публичный контракт ответа не тянет Domain `Entity`/`VO`; используем скаляры, перечисления, `Uuid`, даты и вложенные Response DTO.

## Зависимости

### Разрешено

- Скаляры, типизированные массивы, `BackedEnum`, `DateTimeImmutable`, `UuidInterface/Uuid`.
- Вложенные Response DTO.
- `OpenAPI` и метаданные сериализатора.

### Запрещено

- Метаданные `Validator` и классы пользовательских ограничений.
- Сервисы, репозитории, Domain `Entity`/`VO`, реализации Infrastructure/Integration.

## Расположение

```
apps/<app>/src/Module/<ModuleName>/Controller/<Context>/Response/<Name>ResponseDto.php
```

## Пример

```php
final readonly class ProjectResponseDto
{
    /**
     * @param list<TagResponseDto> $tags
     */
    public function __construct(
        public string $id,
        public string $name,
        public \DateTimeImmutable $createdAt,
        public array $tags,
    ) {
    }
}

final readonly class TagResponseDto
{
    public function __construct(
        public string $name,
    ) {
    }
}
```

Response DTO описывает только внешний контракт ответа: сериализуемые поля, типы и вложенные transport DTO.

## Чек-лист код-ревью

- [ ] DTO описывает только публичный контракт ответа.
- [ ] Нет метаданных `Validator`, `Callback`, `validate*()` и логики в конструкторе.
- [ ] Коллекции и вложенные DTO типизированы.
- [ ] DTO не тянет доменные типы и сервисные зависимости.
