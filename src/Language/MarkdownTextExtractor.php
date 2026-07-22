<?php

declare(strict_types=1);

namespace PrikotovCodingStandard\Language;

/**
 * Извлекатель человекочитаемого текста из Markdown.
 *
 * Удаляет техническое содержимое, оставляя текст для языкового анализа:
 *  - YAML front matter;
 *  - fenced code blocks (``` и ~~~);
 *  - inline code (`...`);
 *  - HTML-теги;
 *  - URL и email;
 *  - namespaces (\Foo\Bar\Baz);
 *  - reference IDs (#123, @user, gh-123);
 *  - плейсхолдеры ({ProjectName});
 *  - латиницу в круглых скобках — переводы терминов и технические уточнения
 *    («обработчик команд (Command Handler)», «(read-only)»);
 *  - латиницу в кавычках-ёмочках — цитирование терминов и примеров
 *    («allowlist», «persisted rows»).
 */
final class MarkdownTextExtractor
{
    public function extract(string $markdown): string
    {
        $text = $markdown;
        $text = $this->stripFrontMatter($text);
        $text = $this->stripHeadings($text);
        $text = $this->stripFencedCodeBlocks($text);
        $text = $this->stripInlineCode($text);
        $text = $this->stripHtmlTags($text);
        $text = $this->stripUrls($text);
        $text = $this->stripEmails($text);
        $text = $this->stripNamespaces($text);
        $text = $this->stripReferences($text);
        $text = $this->stripTaskIds($text);
        $text = $this->stripFilenames($text);
        $text = $this->stripSnakeIdentifiers($text);
        $text = $this->stripPlaceholders($text);
        $text = $this->stripParenthesisedLatin($text);
        $text = $this->stripQuotedLatin($text);

        return $text;
    }

    private function stripFrontMatter(string $text): string
    {
        if (!str_starts_with($text, "---\n")) {
            return $text;
        }
        $end = strpos($text, "\n---\n", 4);
        if ($end === false) {
            return $text;
        }

        return substr($text, $end + 5);
    }

    /**
     * Убирает строки ATX-заголовков (# ... ######) — это структура документа,
     * не running text. Особенно важно для todo-шаблона с английскими секциями.
     */
    private function stripHeadings(string $text): string
    {
        return preg_replace('/^[ \t>]*#{1,6}\s+.*$/m', '', $text) ?? $text;
    }

    private function stripFencedCodeBlocks(string $text): string
    {
        // ``` или ~~~ ... закрывающий забор той же длины.
        return preg_replace(
            '/^[ \t]*([`~]{3,}).*?^[ \t]*\1/ms',
            '',
            $text,
        ) ?? $text;
    }

    private function stripInlineCode(string $text): string
    {
        return preg_replace('/`[^`]*`/', '', $text) ?? $text;
    }

    private function stripHtmlTags(string $text): string
    {
        return preg_replace('/<\/?[A-Za-z][^>]*>/', '', $text) ?? $text;
    }

    private function stripUrls(string $text): string
    {
        return preg_replace('/\b(https?|ftp):\/\/\S+/u', '', $text) ?? $text;
    }

    private function stripEmails(string $text): string
    {
        return preg_replace('/[\w.+-]+@[\w-]+\.[\w.-]+/', '', $text) ?? $text;
    }

    private function stripNamespaces(string $text): string
    {
        // \Foo\Bar\Baz — backslash-separated identifiers.
        return preg_replace(
            '/\\\\?[A-Za-z][A-Za-z0-9_]*(\\\\[A-Za-z][A-Za-z0-9_]*)+/',
            '',
            $text,
        ) ?? $text;
    }

    private function stripReferences(string $text): string
    {
        // #123, gh-123, @user.
        $text = preg_replace('/(gh-)?#\d+/i', '', $text) ?? $text;

        return preg_replace('/\B@[A-Za-z][A-Za-z0-9_-]*/', '', $text) ?? $text;
    }

    private function stripTaskIds(string $text): string
    {
        // TASK-feat-..., EPIC-..., BUG-...
        return preg_replace('/\b(?:TASK|EPIC|BUG)-[A-Za-z0-9-]+/', '', $text) ?? $text;
    }

    private function stripFilenames(string $text): string
    {
        // AGENTS.md, config.php, depfile.yaml.
        return preg_replace('/\b[A-Z][A-Za-z0-9_.-]*\.(md|php|ya?ml|json|xml|txt|sh|ts|js|inc)\b/', '', $text) ?? $text;
    }

    private function stripSnakeIdentifiers(string $text): string
    {
        // UPPER_SNAKE identifiers: AGENTS_TASK_WRITING_GUIDE.
        return preg_replace('/\b[A-Z][A-Z0-9]*_[A-Z0-9_]+\b/', '', $text) ?? $text;
    }

    private function stripPlaceholders(string $text): string
    {
        // {ProjectName}, {ModuleName}.
        return preg_replace('/\{[^{}]*\}/', '', $text) ?? $text;
    }

    /**
     * Удаляет сегменты в круглых скобках, содержащие латиницу —
     * это переводы терминов и технические уточнения, а не англицизмы.
     */
    private function stripParenthesisedLatin(string $text): string
    {
        return preg_replace('/\([^()]*[A-Za-z][^()]*\)/', '', $text) ?? $text;
    }

    /**
     * Удаляет латиницу в кавычках-ёмочках «...» — цитирование терминов
     * и примеров («allowlist», «persisted rows»), а не англицизмы в тексте.
     */
    private function stripQuotedLatin(string $text): string
    {
        return preg_replace('/«[^«»]*[A-Za-z][^«»]*»/u', '', $text) ?? $text;
    }
}
