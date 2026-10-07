<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AiBot
{
    public function email(): string
    {
        return (string) config('llm.bot_email', 'ai@messenger.local');
    }

    public function name(): string
    {
        return (string) config('llm.bot_name', 'Ассистент');
    }

    public function find(): ?User
    {
        return User::query()->where('email', $this->email())->first();
    }

    public function ensureUser(): User
    {
        $existing = $this->find();
        if ($existing) {
            if ($existing->name !== $this->name()) {
                $existing->update(['name' => $this->name()]);
            }

            return $existing->fresh();
        }

        return User::query()->create([
            'name' => $this->name(),
            'email' => $this->email(),
            'password' => Str::random(48),
        ]);
    }

    public function isBot(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return strcasecmp((string) $user->email, $this->email()) === 0;
    }

    public function isBotId(?int $userId): bool
    {
        if (! $userId) {
            return false;
        }

        $bot = $this->find();

        return $bot && (int) $bot->id === (int) $userId;
    }

    /**
     * Идемпотентно создаёт 1:1 диалог пользователя с ботом.
     */
    public function ensureConversation(User $user): ?Conversation
    {
        if ($this->isBot($user)) {
            return null;
        }

        $bot = $this->ensureUser();

        $existing = $user->conversations()
            ->where('type', Conversation::TYPE_DIRECT)
            ->whereHas('users', fn ($q) => $q->where('users.id', $bot->id))
            ->withCount('users')
            ->get()
            ->first(fn (Conversation $c) => (int) $c->users_count === 2);

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $bot) {
            $conversation = Conversation::create([
                'type' => Conversation::TYPE_DIRECT,
                'title' => null,
            ]);
            $conversation->users()->attach([$user->id, $bot->id]);

            return $conversation;
        });
    }
}
