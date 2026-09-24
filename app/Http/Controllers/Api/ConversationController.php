<?php

namespace App\Http\Controllers\Api;

use App\Events\ConversationCreated;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use App\Support\SafeBroadcast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = $user->conversations()
            ->with(['users:id,name,email,avatar_path', 'latestMessage.user:id,name,avatar_path'])
            ->withCount('users')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Conversation $conversation) => $this->serialize($conversation, $user));

        return response()->json($conversations);
    }

    public function store(Request $request): JsonResponse
    {
        $me = $request->user();

        if ($request->filled('user_ids') || $request->filled('title')) {
            return $this->storeGroup($request, $me);
        }

        return $this->storeDirect($request, $me);
    }

    private function storeDirect(Request $request, User $me): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $peerId = (int) $data['user_id'];

        if ($peerId === (int) $me->id) {
            return response()->json(['message' => 'Нельзя создать диалог с собой.'], 422);
        }

        $existing = $me->conversations()
            ->where('type', Conversation::TYPE_DIRECT)
            ->whereHas('users', fn ($q) => $q->where('users.id', $peerId))
            ->withCount('users')
            ->get()
            ->first(fn (Conversation $c) => (int) $c->users_count === 2);

        if ($existing) {
            $existing->load(['users:id,name,email,avatar_path', 'latestMessage.user:id,name,avatar_path']);
            $existing->loadCount('users');

            return response()->json($this->serialize($existing, $me));
        }

        $conversation = DB::transaction(function () use ($me, $peerId) {
            $conversation = Conversation::create([
                'type' => Conversation::TYPE_DIRECT,
                'title' => null,
            ]);
            $conversation->users()->attach([$me->id, $peerId]);

            return $conversation;
        });

        $conversation->load(['users:id,name,email,avatar_path', 'latestMessage.user:id,name,avatar_path']);
        $conversation->loadCount('users');

        $this->broadcastCreated($conversation, $me, [$peerId]);

        return response()->json($this->serialize($conversation, $me), 201);
    }

    private function storeGroup(Request $request, User $me): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:1', 'max:100'],
            'user_ids' => ['required', 'array', 'min:2', 'max:20'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id', Rule::notIn([(int) $me->id])],
        ]);

        $title = trim($data['title']);
        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => ['Укажите название группы.'],
            ]);
        }

        $memberIds = array_values(array_unique(array_map('intval', $data['user_ids'])));
        if (count($memberIds) < 2) {
            throw ValidationException::withMessages([
                'user_ids' => ['Выберите минимум двух участников.'],
            ]);
        }

        $conversation = DB::transaction(function () use ($me, $title, $memberIds) {
            $conversation = Conversation::create([
                'type' => Conversation::TYPE_GROUP,
                'title' => $title,
            ]);
            $conversation->users()->attach(array_values(array_unique(array_merge([$me->id], $memberIds))));

            return $conversation;
        });

        $conversation->load(['users:id,name,email,avatar_path', 'latestMessage.user:id,name,avatar_path']);
        $conversation->loadCount('users');

        $this->broadcastCreated($conversation, $me, $memberIds);

        return response()->json($this->serialize($conversation, $me), 201);
    }

    private function broadcastCreated(Conversation $conversation, User $creator, array $recipientIds): void
    {
        foreach ($recipientIds as $recipientId) {
            $recipientId = (int) $recipientId;
            if ($recipientId === (int) $creator->id) {
                continue;
            }

            $recipient = $conversation->users->firstWhere('id', $recipientId);
            if (! $recipient) {
                continue;
            }

            SafeBroadcast::now(new ConversationCreated(
                $recipientId,
                $this->serialize($conversation, $recipient)
            ));
        }
    }

    private function serialize(Conversation $conversation, User $user): array
    {
        $type = $conversation->type ?: Conversation::TYPE_DIRECT;
        $peer = $type === Conversation::TYPE_DIRECT ? $conversation->peerFor($user) : null;
        $last = $conversation->latestMessage;
        $membersCount = (int) ($conversation->users_count ?? $conversation->users->count());

        $payload = [
            'id' => $conversation->id,
            'type' => $type,
            'title' => $conversation->title,
            'members_count' => $membersCount,
            'peer' => $peer ? [
                'id' => $peer->id,
                'name' => $peer->name,
                'email' => $peer->email,
                'avatar_url' => $peer->avatar_url,
            ] : null,
            'last_message' => $last ? [
                'id' => $last->id,
                'body' => $last->body,
                'user_id' => $last->user_id,
                'created_at' => $last->created_at,
            ] : null,
            'updated_at' => $conversation->updated_at,
        ];

        if ($type === Conversation::TYPE_GROUP) {
            $payload['members'] = $conversation->users
                ->take(5)
                ->map(fn (User $member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'avatar_url' => $member->avatar_url,
                ])
                ->values()
                ->all();
        }

        return $payload;
    }

    public static function assertParticipant(Conversation $conversation, User $user): void
    {
        if (! $conversation->hasParticipant($user->id)) {
            throw new AccessDeniedHttpException('Нет доступа к диалогу.');
        }
    }
}
