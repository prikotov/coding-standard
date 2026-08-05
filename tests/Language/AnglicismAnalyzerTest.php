<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Tests\Language;

use PHPUnit\Framework\TestCase;
use PrikotovCodingStandard\Language\AnglicismAnalyzer;
use PrikotovCodingStandard\Language\MarkdownTextExtractor;

final class AnglicismAnalyzerTest extends TestCase
{
    public function testPureRussianTextHasZeroAnglicisms(): void
    {
        $analyzer = new AnglicismAnalyzer();

        $result = $analyzer->analyze('Это обычный русский текст без англицизмов.');

        self::assertSame(0, $result->anglicismWords);
        self::assertSame(0.0, $result->ratio);
    }

    public function testDetectsAnglicismWords(): void
    {
        $analyzer = new AnglicismAnalyzer();

        $result = $analyzer->analyze('Мы сохраняем persisted rows в базу данных.');

        self::assertSame(2, $result->anglicismWords);
        self::assertContains('persisted', $result->sampleWords);
        self::assertContains('rows', $result->sampleWords);
    }

    public function testAllowlistedTermsAreNotAnglicisms(): void
    {
        $analyzer = new AnglicismAnalyzer(['Symfony', 'DTO']);

        // Symfony и DTO — в allowlist.
        $result = $analyzer->analyze('Используем Symfony и DTO в проекте.');

        self::assertSame(0, $result->anglicismWords);
    }

    public function testExtraAllowlistSuppressesTerms(): void
    {
        $analyzer = new AnglicismAnalyzer(['sniff', 'custom']);

        $result = $analyzer->analyze('Добавили custom sniff в стандарт.');

        self::assertSame(0, $result->anglicismWords);
    }

    public function testAllowlistedOnlyEnglishLineHasNoAnglicisms(): void
    {
        $analyzer = new AnglicismAnalyzer(['Symfony', 'PHPUnit', 'PHPStan']);

        // Строка целиком из allowlist-терминов — англицизмов нет.
        $result = $analyzer->analyze('Symfony PHPUnit PHPStan');

        self::assertSame(0, $result->anglicismWords);
    }

    public function testRatioCalculation(): void
    {
        $analyzer = new AnglicismAnalyzer();

        // 5 слов: «текст», «persisted», «rows», «база», «данных».
        // Англицизмы (не allowlist): persisted, rows = 2.
        $result = $analyzer->analyze('текст persisted rows база данных');

        self::assertSame(5, $result->totalWords);
        self::assertSame(2, $result->anglicismWords);
        self::assertSame(0.4, $result->ratio);
    }

    public function testSingleEnglishWordCountsAsAnglicism(): void
    {
        $analyzer = new AnglicismAnalyzer();

        // Одиночное «allowlist» в русском тексте — англицизм, входит в ratio.
        // (Термины в backticks исключаются экстрактором до analyze — см. MarkdownTextExtractorTest.)
        $result = $analyzer->analyze('Слова вне allowlist не входят в метрику.');

        self::assertSame(1, $result->anglicismWords);
        self::assertContains('allowlist', $result->sampleWords);
    }

    public function testHyphenatedWordCountedAsOne(): void
    {
        $analyzer = new AnglicismAnalyzer();

        // «read-only» — один токен с дефисом.
        $result = $analyzer->analyze('используем read-only режим');

        self::assertSame(1, $result->anglicismWords);
    }

    public function testInlineCodeTermExcludedViaExtractorPipeline(): void
    {
        // Сценарий из конвенции: `ratio` в backticks — inline code, исключается
        // экстрактором; одиночный англицизм allowlist — ловится.
        $extractor = new MarkdownTextExtractor();
        $analyzer = new AnglicismAnalyzer();

        $prose = $extractor->extract('Слова вне allowlist не входят в `ratio`.');
        $result = $analyzer->analyze($prose);

        self::assertSame(1, $result->anglicismWords);
        self::assertContains('allowlist', $result->sampleWords);
    }

    public function testEmptyText(): void
    {
        $analyzer = new AnglicismAnalyzer();

        $result = $analyzer->analyze('');

        self::assertSame(0, $result->totalWords);
        self::assertSame(0.0, $result->ratio);
    }

    public function testDddLayerTermsAreAllowed(): void
    {
        $analyzer = new AnglicismAnalyzer(['Domain', 'Service', 'Repository']);

        // Domain, Service, Repository — названия слоёв/паттернов в allowlist.
        $result = $analyzer->analyze('Domain Service возвращает Repository.');

        self::assertSame(0, $result->anglicismWords);
    }

    public function testDictionarySuggestsTranslationForAnglicismWord(): void
    {
        $analyzer = new AnglicismAnalyzer([], ['hook' => 'хук']);

        $result = $analyzer->analyze('Регистрируем hook на событие.');

        self::assertSame(1, $result->anglicismWords);
        self::assertContains('hook', $result->sampleWords);
        self::assertSame(['hook' => 'хук'], $result->suggestions);
    }

    public function testDictionaryDoesNotSuggestAbsentWord(): void
    {
        // Слова из dictionary нет в тексте — подсказки быть не должно.
        $analyzer = new AnglicismAnalyzer([], ['hook' => 'хук']);

        $result = $analyzer->analyze('Чистый русский текст без англицизмов.');

        self::assertSame([], $result->suggestions);
    }

    public function testDictionaryDoesNotSuggestAllowlistedWord(): void
    {
        // Слово в allowlist — не англицизм, подсказки нет (контракт термина достаточен).
        $analyzer = new AnglicismAnalyzer(['hook'], ['hook' => 'хук']);

        $result = $analyzer->analyze('Регистрируем hook на событие.');

        self::assertSame(0, $result->anglicismWords);
        self::assertSame([], $result->suggestions);
    }

    public function testWithoutDictionaryHasNoSuggestions(): void
    {
        // Обратная совместимость: без dictionary подсказок нет.
        $analyzer = new AnglicismAnalyzer();

        $result = $analyzer->analyze('Регистрируем hook на событие.');

        self::assertSame(1, $result->anglicismWords);
        self::assertSame([], $result->suggestions);
    }

    public function testDictionarySuggestsMultiWordPhrase(): void
    {
        $analyzer = new AnglicismAnalyzer([], ['god object' => 'божественный объект']);

        $result = $analyzer->analyze('Это типичный god object в кодовой базе.');

        self::assertSame(['god object' => 'божественный объект'], $result->suggestions);
    }

    public function testDictionaryMatchingIsCaseInsensitive(): void
    {
        // Ключ dictionary и слово в тексте в разном регистре — совпадает.
        $analyzer = new AnglicismAnalyzer([], ['Hook' => 'хук']);

        $result = $analyzer->analyze('Регистрируем HOOK на событие.');

        self::assertSame(['hook' => 'хук'], $result->suggestions);
    }

    public function testDictionaryIgnoresPhraseWhenAllWordsAllowlisted(): void
    {
        // Все слова фразы — allowlist-термины: англицизма нет, подсказка не шумит.
        $analyzer = new AnglicismAnalyzer(
            ['service', 'layer'],
            ['service layer' => 'слой служб'],
        );

        $result = $analyzer->analyze('service layer приложения');

        self::assertSame([], $result->suggestions);
    }

    public function testAllowlistedPhraseSuppressesWords(): void
    {
        // Многословная фраза в allowlist — все её слова исключаются из подсчёта.
        $analyzer = new AnglicismAnalyzer(['Unit of Work']);

        $result = $analyzer->analyze('Репозиторий не управляет Unit of Work.');

        self::assertSame(0, $result->anglicismWords);
    }

    public function testAllowlistedPhraseIsCaseInsensitive(): void
    {
        // Фраза в тексте в другом регистре — совпадает.
        $analyzer = new AnglicismAnalyzer(['Unit of Work']);

        $result = $analyzer->analyze('Контроль через UNIT OF WORK.');

        self::assertSame(0, $result->anglicismWords);
    }
}
