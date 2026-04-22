# Response DTO презентационного слоя (Presentation Response DTO)

## Определение

**Presentation Response DTO** — transport-модель публичного ответа Presentation-слоя, которую controller сериализует
в JSON, HTML context или другой внешний contract после вызова Application-слоя.

## Общие правила

- `ResponseDto` объявляем как `final readonly class`.
- DTO описывает только внешний response contract и не содержит validation logic.
- Разрешена metadata, влияющая на сериализацию и документацию ответа:
  OpenAPI attributes, serializer metadata, PHPDoc для коллекций.
- `ResponseDto` не должен содержать `Assert`, custom validators, `Callback`, `validate*()` и constructor logic.
- Нормализация ответа выполняется в mapper/controller до создания DTO, а не внутри DTO.
- Публичный response contract не тянет Domain Entity/VO; используем scalars, enums, `Uuid`, даты и вложенные response DTO.

## Зависимости

### Разрешено

- Скаляры, типизированные массивы, `BackedEnum`, `DateTimeImmutable`, `UuidInterface/Uuid`.
- Вложенные response DTO.
- OpenAPI и serializer metadata.

### Запрещено

- Validator metadata и custom constraint classes.
- Сервисы, репозитории, Domain Entity/VO, Infrastructure/Integration implementations.

## Расположение

```
apps/<app>/src/Module/<ModuleName>/Controller/<Context>/Response/<Name>ResponseDto.php
```

Для truly cross-cutting response contract допускается `apps/<app>/src/Component/<Context>/`.

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

`ResponseDto` описывает только внешний contract ответа: сериализуемые поля, типы и вложенные transport DTO.

## Чек-лист код-ревью

- [ ] DTO описывает только публичный response contract.
- [ ] Нет validator metadata, `Callback`, `validate*()` и constructor logic.
- [ ] Коллекции и вложенные DTO типизированы.
- [ ] DTO не тянет доменные типы и сервисные зависимости.
