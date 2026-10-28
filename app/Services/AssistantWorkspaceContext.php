<?php

namespace App\Services;

use App\Models\AudioClip;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\TaskItem;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Support\Collection;

class AssistantWorkspaceContext
{
    public function __construct(
        private AiBot $aiBot,
        private AttachmentTextExtractor $extractor,
    ) {
    }

    /**
     * Короткий каталог для роутера (только названия, без тел).
     */
    public function buildCatalog(User $user): string
    {
        $botId = $this->aiBot->find()?->id;
        $conversations = $this->participantConversations($user, $botId);
        $lines = ['Доступные источники (кратко):'];

        foreach ($conversations->take(20) as $conversation) {
            /** @var Conversation $conversation */
            $lines[] = '- Чат: '.$this->conversationLabel($conversation, $user);
        }

        $clipCount = AudioClip::query()->where('user_id', $user->id)->count();
        $taskCount = TaskList::query()->where('user_id', $user->id)->count();
        $fileCount = $this->userAttachments($user, $botId, 100)->count();

        $lines[] = "- Файлов во вложениях: {$fileCount}";
        $lines[] = "- Аудио-записей: {$clipCount}";
        $lines[] = "- Списков задач: {$taskCount}";

        if ($conversations->isEmpty() && $clipCount === 0 && $taskCount === 0) {
            $lines[] = '(пока нет чатов, файлов, записей и задач)';
        }

        return implode("\n", $lines);
    }

    /**
     * Полный текстовый пакет для reply-вызова.
     * $query — текст запроса пользователя: приоритет чатов/файлов по совпадению имён.
     */
    public function build(User $user, ?string $query = null): string
    {
        $maxChars = max(1500, (int) config('llm.context_max_chars', 8000));
        $maxMessagesTotal = max(8, (int) config('llm.context_max_messages_total', 60));
        $maxPerChat = max(5, (int) config('llm.context_max_messages_per_conversation', 25));
        $maxAttachments = max(3, (int) config('llm.context_max_attachments', 10));
        $maxClips = max(3, (int) config('llm.context_max_clips', 8));

        $botId = $this->aiBot->find()?->id;
        $tokens = $this->queryTokens($query);
        $parts = [];
        $used = 0;
        $truncated = false;

        $append = function (string $chunk) use (&$parts, &$used, &$truncated, $maxChars): bool {
            $chunk = trim($chunk);
            if ($chunk === '') {
                return true;
            }
            $len = mb_strlen($chunk) + 2;
            if ($used + $len > $maxChars) {
                $remaining = $maxChars - $used - 80;
                if ($remaining > 200) {
                    $parts[] = mb_substr($chunk, 0, $remaining)."\n…[усечено]";
                    $used = $maxChars;
                }
                $truncated = true;

                return false;
            }
            $parts[] = $chunk;
            $used += $len;

            return true;
        };

        $conversations = $this->rankConversations(
            $this->participantConversations($user, $botId),
            $user,
            $tokens
        );

        $focusOnly = $tokens !== [] && $conversations->contains(
            fn (Conversation $c) => $this->conversationMatches($c, $user, $tokens)
        );

        $header = "=== Контекст данных пользователя ===\n".$this->buildCatalog($user);
        if ($focusOnly) {
            $labels = $conversations
                ->filter(fn (Conversation $c) => $this->conversationMatches($c, $user, $tokens))
                ->map(fn (Conversation $c) => $this->conversationLabel($c, $user))
                ->values()
                ->all();
            $header .= "\nФокус запроса: ".implode(', ', $labels);
        }
        if (! $append($header)) {
            return implode("\n\n", $parts);
        }

        $wantsTasks = $this->queryWants($tokens, ['задач', 'дела', 'todo', 'to-do']);
        $wantsAudio = $this->queryWants($tokens, ['аудио', 'запис', 'клип', 'транскрип', 'голоса']);
        $wantsDocs = $this->queryWants($tokens, ['документ', 'файл', 'вложен', 'pdf', 'docx']);
        $wantsChats = $tokens === []
            || $this->queryWants($tokens, ['переписк', 'чат', 'диалог', 'боб', 'сообщен', 'тезис', 'выдержк', 'суммир', 'резюм'])
            || $focusOnly
            || (! $wantsTasks && ! $wantsAudio && ! $wantsDocs);

        if (! $truncated && ($wantsTasks || $tokens === [])) {
            $taskBlock = $this->formatTaskLists($user);
            if ($taskBlock !== '') {
                $append($taskBlock);
            }
        }

        if ($wantsChats && ! $truncated) {
            $messagesLeft = $maxMessagesTotal;
            $matchedAny = false;

            foreach ($conversations as $conversation) {
                if ($messagesLeft <= 0 || $truncated) {
                    break;
                }

                /** @var Conversation $conversation */
                if ($focusOnly && ! $this->conversationMatches($conversation, $user, $tokens)) {
                    continue;
                }

                $matchedAny = true;
                $label = $this->conversationLabel($conversation, $user);
                $headerChat = "--- Чат #{$conversation->id}: {$label} ---";

                $messages = $conversation->messages()
                    ->with(['user:id,name', 'attachments'])
                    ->orderByDesc('id')
                    ->limit(min($maxPerChat, $messagesLeft))
                    ->get()
                    ->sortBy('id')
                    ->values();

                $messagesLeft -= $messages->count();
                $bodyLines = [];
                foreach ($messages as $message) {
                    /** @var Message $message */
                    $who = $message->user?->name ?: ('user#'.$message->user_id);
                    $when = optional($message->created_at)->format('Y-m-d H:i') ?: '';
                    $text = trim((string) ($message->body ?? ''));
                    $line = "[{$when}] {$who}: ".($text !== '' ? $text : '(без текста)');
                    if ($message->attachments->isNotEmpty()) {
                        $names = $message->attachments->pluck('original_name')->implode(', ');
                        $line .= ' [вложения: '.$names.']';
                    }
                    $bodyLines[] = $line;
                }

                if (! $append($headerChat."\n".implode("\n", $bodyLines))) {
                    break;
                }
            }

            if ($focusOnly && ! $matchedAny) {
                $append('По запросу не найден подходящий чат среди доступных диалогов.');
            }
        }

        if (! $truncated && ($wantsDocs || $tokens === [])) {
            $attachments = $this->userAttachments($user, $botId, $maxAttachments);
            if ($attachments->isNotEmpty()) {
                $docParts = ['--- Документы (извлечённый текст) ---'];
                foreach ($attachments as $attachment) {
                    /** @var MessageAttachment $attachment */
                    $this->extractor->ensureExtracted($attachment);
                    $attachment->refresh();
                    $meta = $attachment->original_name.' ('.($attachment->mime ?: '?').')';
                    if ($attachment->extract_status === MessageAttachment::EXTRACT_DONE
                        && trim((string) ($attachment->extracted_text ?? '')) !== '') {
                        $text = trim((string) $attachment->extracted_text);
                        // Не раздувать контекст одним файлом
                        $docParts[] = "Файл: {$meta}\n".mb_substr($text, 0, 3000);
                    } else {
                        $reason = $attachment->extract_error ?: 'текст недоступен';
                        $docParts[] = "Файл: {$meta} — {$reason}";
                    }
                }
                if (count($docParts) > 1) {
                    $append(implode("\n\n", $docParts));
                }
            }
        }

        if (! $truncated && ($wantsAudio || $tokens === [])) {
            $clips = AudioClip::query()
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->limit($maxClips)
                ->get();

            if ($clips->isNotEmpty()) {
                $clipParts = ['--- Аудио-записи (транскрипты) ---'];
                foreach ($clips as $clip) {
                    $when = optional($clip->created_at)->format('Y-m-d H:i') ?: '?';
                    if ($clip->transcript_status === AudioClip::TRANSCRIPT_DONE
                        && trim((string) ($clip->transcript_text ?? '')) !== '') {
                        $text = trim((string) $clip->transcript_text);
                        $clipParts[] = "Клип #{$clip->id} ({$when}):\n".mb_substr($text, 0, 2500);
                    } else {
                        $clipParts[] = "Клип #{$clip->id} ({$when}): транскрипт ещё не готов (".$clip->transcript_status.')';
                    }
                }
                $append(implode("\n\n", $clipParts));
            }
        }

        if ($truncated) {
            $parts[] = '…остальное опущено из‑за лимита контекста.';
        }

        return implode("\n\n", $parts);
    }

    /**
     * @return list<string>
     */
    private function queryTokens(?string $query): array
    {
        $query = mb_strtolower(trim((string) $query));
        if ($query === '') {
            return [];
        }

        $parts = preg_split('/[^\p{L}\p{N}]+/u', $query) ?: [];
        $stop = [
            'сделай', 'краткую', 'кратко', 'выдержку', 'выдержка', 'переписки', 'переписка',
            'не', 'более', 'пяти', 'тезисов', 'тезис', 'что', 'ты', 'можешь', 'мне', 'мой',
            'мои', 'моих', 'про', 'для', 'или', 'как', 'это', 'пожалуйста', 'опиши', 'суммируй',
        ];

        $tokens = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (mb_strlen($part) < 3 || in_array($part, $stop, true)) {
                continue;
            }
            $tokens[] = $part;
        }

        return array_values(array_unique($tokens));
    }

    /**
     * @param  list<string>  $tokens
     * @param  list<string>  $needles
     */
    private function queryWants(array $tokens, array $needles): bool
    {
        foreach ($tokens as $token) {
            foreach ($needles as $needle) {
                if (str_contains($token, $needle) || str_contains($needle, $token)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, Conversation>  $conversations
     * @param  list<string>  $tokens
     * @return Collection<int, Conversation>
     */
    private function rankConversations(Collection $conversations, User $user, array $tokens): Collection
    {
        if ($tokens === []) {
            return $conversations;
        }

        return $conversations->sortByDesc(function (Conversation $conversation) use ($user, $tokens) {
            return $this->conversationMatches($conversation, $user, $tokens) ? 1 : 0;
        })->values();
    }

    /**
     * @param  list<string>  $tokens
     */
    private function conversationMatches(Conversation $conversation, User $user, array $tokens): bool
    {
        $label = mb_strtolower($this->conversationLabel($conversation, $user));
        $title = mb_strtolower((string) ($conversation->title ?? ''));
        $hay = $label.' '.$title;
        foreach ($conversation->users as $member) {
            $hay .= ' '.mb_strtolower((string) $member->name);
        }

        return $this->textMatches($hay, $tokens);
    }

    /**
     * @param  list<string>  $tokens
     */
    private function textMatches(string $haystack, array $tokens): bool
    {
        $haystack = mb_strtolower($haystack);
        foreach ($tokens as $token) {
            if (str_contains($haystack, $token)) {
                return true;
            }
            // падежи: «Ботом»/«Бобом» → стебель «боб»
            $stemLen = max(3, mb_strlen($token) - 2);
            $stem = mb_substr($token, 0, $stemLen);
            if (mb_strlen($stem) >= 3 && str_contains($haystack, $stem)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, Conversation>
     */
    private function participantConversations(User $user, ?int $botId): Collection
    {
        return Conversation::query()
            ->whereHas('users', fn ($q) => $q->where('users.id', $user->id))
            ->when($botId, function ($q) use ($botId) {
                $q->where(function ($outer) use ($botId) {
                    $outer->where('type', Conversation::TYPE_GROUP)
                        ->orWhere(function ($direct) use ($botId) {
                            $direct->where('type', Conversation::TYPE_DIRECT)
                                ->whereDoesntHave('users', fn ($uq) => $uq->where('users.id', $botId));
                        });
                });
            })
            ->with(['users:id,name,email'])
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();
    }

    /**
     * @return Collection<int, MessageAttachment>
     */
    private function userAttachments(User $user, ?int $botId, int $limit): Collection
    {
        $conversationIds = $this->participantConversations($user, $botId)->pluck('id');
        if ($conversationIds->isEmpty()) {
            return collect();
        }

        return MessageAttachment::query()
            ->whereHas('message', fn ($q) => $q->whereIn('conversation_id', $conversationIds))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    private function conversationLabel(Conversation $conversation, User $user): string
    {
        if ($conversation->isGroup()) {
            $title = trim((string) ($conversation->title ?? ''));

            return $title !== '' ? $title : 'Группа #'.$conversation->id;
        }

        $conversation->loadMissing('users:id,name');
        $peer = $conversation->peerFor($user);

        return $peer?->name ?: ('Диалог #'.$conversation->id);
    }

    private function formatTaskLists(User $user): string
    {
        $lists = TaskList::query()
            ->where('user_id', $user->id)
            ->with(['items' => fn ($q) => $q->orderBy('position')->orderBy('id')])
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        if ($lists->isEmpty()) {
            return "--- Списки задач ---\n(пока нет сохранённых списков задач из аудио)";
        }

        $parts = ['--- Списки задач ---'];
        foreach ($lists as $list) {
            /** @var TaskList $list */
            $when = optional($list->created_at)->format('Y-m-d H:i') ?: '';
            $parts[] = 'Список #'.$list->id.' «'.($list->title ?: 'Задачи').'»'
                .' (клип #'.(int) $list->audio_clip_id.', '.$when.'):';
            foreach ($list->items as $item) {
                /** @var TaskItem $item */
                $mark = $item->status === TaskItem::STATUS_DONE ? '[x]' : '[ ]';
                $parts[] = '  '.$mark.' '.$item->body;
            }
            if ($list->items->isEmpty()) {
                $parts[] = '  (пунктов нет)';
            }
        }

        return implode("\n", $parts);
    }
}
