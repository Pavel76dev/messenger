<?php

namespace App\Services;

use App\Models\User;

/**
 * Собирает промпт для LLM: фиксированные правила в коде + пожелания пользователя.
 */
class TasksExtractPromptBuilder
{
    public function __construct(
        private UserSettings $userSettings,
    ) {
    }

    /**
     * @return array{0: string, 1: string} [system, userMessage]
     */
    public function build(User $owner, string $transcript): array
    {
        $system = <<<'PROMPT'
Ты составляешь список дел из текста (транскрипт речи или рассказ) на русском.

Найди все действия, которые нужно сделать: поручения, обязательства, «нужно/стоит/предстоит/не забыть», дедлайны, звонки, письма, проверки, записи к специалистам, покупки, согласования.
Переформулируй каждый пункт коротко, с глагола. Если указан срок или адресат — включи в пункт.
Разбивай составные фразы («и», «а заодно») на отдельные пункты.
Игнорируй только чистые приветствия/прощания и болтовню без действий.

Ответ — ТОЛЬКО один JSON без markdown и пояснений:
{"title":"краткий заголовок","tasks":[{"text":"пункт"}]}
Если действий нет: {"title":"","tasks":[]}

Пример: текст «До пятницы согласуй смету и позвони Ивану» →
{"title":"Дела","tasks":[{"text":"Согласовать смету до пятницы"},{"text":"Позвонить Ивану"}]}
PROMPT;

        $wishes = trim($this->userSettings->get($owner, 'ai.tasks_extract_wishes'));
        if ($wishes !== '') {
            $system .= "\n\nПожелания пользователя (учитывай их):\n".$wishes;
        }

        $userMessage = "Составь список задач по тексту ниже.\n\nТекст:\n".$transcript;

        return [$system, $userMessage];
    }
}
