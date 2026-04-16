# Twig-расширение (Twig Extension)

## Определение

**Twig-расширение (Twig Extension)** — presentation-механизм Twig для выноса повторяющихся вычислений и форматирования в единое место.
Детали по механике см. в [Symfony Twig Extensions](https://symfony.com/doc/current/templating/twig_extension.html).

Цель Twig Extension:
- уменьшить дублирование presentation-логики в шаблонах;
- обеспечить единые правила преобразования и форматирования данных;
- упростить Twig-шаблоны за счёт переноса вычислений из разметки в PHP-код.

## Общие правила

- Twig Extension объявляем `final` и наследуем от `Twig\Extension\AbstractExtension`.
- Регистрируем extension как сервис с тегом `twig.extension` в `apps/<app>/config/services.yaml`.
- Публичные функции extension должны быть детерминированными и побочно-безопасными.
- В extension допускается презентационная логика: нормализация строк, truncation, сборка meta-map, выбор display-классов/лейблов.
- В extension запрещена бизнес-логика домена, вызовы UseCase, доступ к репозиториям, ORM и внешним API.
- Если функция возвращает HTML, указываем `is_safe => ['html']` только при явной необходимости. Предпочтительно возвращать структурированные данные (array/scalar), а HTML рендерить в Twig.

## Зависимости

- Разрешено: `TranslatorInterface`, route/helper-сервисы Presentation, утилиты форматирования уровня Presentation.
- Запрещено: зависимости из `Domain/*`, `Infrastructure/*`, `Integration/*`.

## Расположение

```
apps/<app>/src/Component/Twig/Extension/<Name>Extension.php
apps/<app>/tests/Unit/Component/Twig/Extension/<Name>ExtensionTest.php
```

## Как используем

1. Создаём extension-класс и регистрируем TwigFunction/TwigFilter.
2. Выносим в extension повторяющуюся презентационную логику (нормализация, форматирование, подготовка map-структур).
3. Подключаем extension-функцию в шаблоне и рендерим только результат.
4. Добавляем unit-тест extension (fallback, truncation, normalization, контракт ключей).
5. Добавляем integration-тест шаблона/контроллера на итоговый HTML-контракт.

## Когда использовать Twig Extension

- Когда нужно переиспользовать вычисление или форматирование данных в нескольких шаблонах.
- Когда повторяются Twig-выражения (`normalize`, `format`, `map`, route-aware подготовка данных) и их нужно вынести из разметки.
- Когда основной результат — данные (`string`, `bool`, `array`, число) или служебный HTML малого объёма.
- Когда требуется единый контракт функции/фильтра для presentation-логики без создания отдельного UI-блока.
- Для правил по Twig Component используем документ [Twig-компонент (Twig Component)](twig_component.md).

### Быстрый decision checklist

- [ ] Нужна функция/фильтр для вычислений и подготовки данных -> `Twig Extension`.
- [ ] После удаления HTML из решения остается ценная логика данных -> `Twig Extension`.
- [ ] В шаблоне повторяются вычисления, которые можно стабилизировать в общем API extension -> `Twig Extension`.

## Пример

```php
<?php

declare(strict_types=1);

namespace Web\Component\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class FileSizeExtension extends AbstractExtension
{
    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('format_filesize', [$this, 'formatBytes']),
        ];
    }

    public function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytesFloat = (float) max($bytes, 0);
        $pow = $bytesFloat > 0 ? floor(log($bytesFloat) / log(1024)) : 0;
        $pow = min($pow, count($units) - 1);
        $bytesFloat = $bytesFloat / pow(1024.0, $pow);

        return (string) round($bytesFloat, $precision) . ' ' . $units[(int) $pow];
    }
}
```

```twig
{{ model.sizeBytes|format_filesize }}
```

## Чек-лист ревью

- [ ] Extension расположен в `Component/Twig/Extension` и зарегистрирован как `twig.extension`.
- [ ] Шаблон не содержит сложной вычислительной логики, только рендерит подготовленные данные.
- [ ] Нет импортов и зависимостей из Domain/Infrastructure/Integration.
- [ ] Есть unit-тест на extension и integration-тест на HTML-контракт страницы.
- [ ] Для выбранного сценария есть единый контракт данных и fallback-значения (если применимо).
