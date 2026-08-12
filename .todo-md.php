<?php

declare(strict_types=1);

// Project-level configuration for todo-md validate.
// Lists canonical roles and agents for author/assignee validation.
// Full reference: docs/todo-md/reference/CONFIG.md
return [
    // Канонические роли проекта (текст перед скобками). Пусто/отсутствует — роль
    // проверяется только по формату. Раскомментируйте и дополните под проект:
    // 'roles' => ['Бэкендер', 'Фронтендер', 'Девопс', 'Аналитик', 'Архитектор'],

    // Канонические агенты (lowercase-идентификатор в скобках). Пусто/отсутствует —
    // используется пакетный список из reference/AI_AGENTS.md.
    // 'agents' => ['codex-cli', 'codex', 'pi', 'kilocode'],

    // Активные задачи должны использовать канонический формат ролей и агентов.
    'strict' => true,
];
