<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = $user->conversations()
            ->with(['users:id,name,email', 'latestMessage.user:id,name'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Conversation $conversation) => $this->serialize($conversation, $user));

        return response()->json($conversations);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $me = $request->user();
        $peerId = (int) $data['user_id'];

        if ($peerId === (int) $me->id) {
            return response()->json(['message' => 'Нельзя создать диалог с собой.'], 422);
        }

        $existing = $me->conversations()
            ->whereHas('users', fn ($q) => $q->where('users.id', $peerId))
            ->withCount('users')
            ->get()
            ->first(fn (Conversation $c) => (int) $c->users_count === 2);

        if ($existing) {
            $existing->load(['users:id,name,email', 'latestMessage.user:id,name']);

            return response()->json($this->serialize($existing, $me));
        }

        $conversation = DB::transaction(function () use ($me, $peerId) {
            $conversation = Conversation::create();
            $conversation->users()->attach([$me->id, $peerId]);

            return $conversation;
        });

        $conversation->load(['users:id,name,email', 'latestMessage.user:id,name']);

        return response()->json($this->serialize($conversation, $me), 201);
    }

    private function serialize(Conversation $conversation, User $user): array
    {
        $peer = $conversation->peerFor($user);
        $last = $conversation->latestMessage;

        return [
            'id' => $conversation->id,
            'peer' => $peer ? [
                'id' => $peer->id,
                'name' => $peer->name,
                'email' => $peer->email,
            ] : null,
            'last_message' => $last ? [
                'id' => $last->id,
                'body' => $last->body,
                'user_id' => $last->user_id,
                'created_at' => $last->created_at,
            ] : null,
            'updated_at' => $conversation->updated_at,
        ];
    }

    public static function assertParticipant(Conversation $conversation, User $user): void
    {
        if (! $conversation->hasParticipant($user->id)) {
            throw new AccessDeniedHttpException('Нет доступа к диалогу.');
        }
    }
}
