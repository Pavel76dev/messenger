<?php

namespace App\Http\Controllers\Api;

use App\Events\CallAccepted;
use App\Events\CallEnded;
use App\Events\CallIncoming;
use App\Events\CallRejected;
use App\Events\CallUpdated;
use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Conversation;
use App\Models\User;
use App\Services\LiveKitTokenService;
use App\Support\SafeBroadcast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CallController extends Controller
{
    public function __construct(
        private LiveKitTokenService $liveKit,
    ) {
    }

    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        ConversationController::assertParticipant($conversation, $user);
        Call::expireStaleForConversation((int) $conversation->id);

        $data = $request->validate([
            'media_type' => ['required', 'in:audio,video'],
        ]);

        $openCall = Call::query()
            ->where('conversation_id', $conversation->id)
            ->whereIn('status', [Call::STATUS_RINGING, Call::STATUS_ACTIVE])
            ->with(['conversation.users:id,name,email,avatar_path', 'creator:id,name,avatar_path', 'participantRows.user:id,name,avatar_path'])
            ->latest('id')
            ->first();

        if ($openCall) {
            // Создатель может вернуться в свой ещё звонящий звонок (после F5)
            if ((int) $openCall->created_by === (int) $user->id && $openCall->isOpen()) {
                $openCall->markJoined($user);

                return response()->json([
                    'call' => $this->serialize($openCall),
                    'token' => $this->liveKit->createToken($user, $openCall->room_name),
                    'livekit_url' => $this->liveKit->wsUrl(),
                    'resumed' => true,
                ]);
            }

            throw new ConflictHttpException('В этом чате уже идёт звонок.');
        }

        $isGroup = $conversation->isGroup();

        $call = Call::query()->create([
            'conversation_id' => $conversation->id,
            'created_by' => $user->id,
            'room_name' => 'call-'.Str::uuid()->toString(),
            'media_type' => $data['media_type'],
            'status' => $isGroup ? Call::STATUS_ACTIVE : Call::STATUS_RINGING,
            'started_at' => $isGroup ? now() : null,
        ]);

        $call->markJoined($user);
        $call->load(['conversation.users:id,name,email,avatar_path', 'creator:id,name,avatar_path', 'participantRows.user:id,name,avatar_path']);

        $payload = $this->serialize($call);
        SafeBroadcast::now(new CallIncoming($payload, $this->broadcastChannels($call, excludeUserId: (int) $user->id)));

        return response()->json([
            'call' => $payload,
            'token' => $this->liveKit->createToken($user, $call->room_name),
            'livekit_url' => $this->liveKit->wsUrl(),
        ], 201);
    }

    public function accept(Request $request, Call $call): JsonResponse
    {
        $user = $request->user();
        $this->assertCallAccess($call, $user);

        if ((int) $call->created_by === (int) $user->id) {
            throw new ConflictHttpException('Нельзя принять собственный звонок.');
        }

        // Уже принят (повторный клик / гонка) — просто выдаём token
        if ($call->status === Call::STATUS_ACTIVE) {
            $call->markJoined($user);
            $call->touch();
            $call->load(['conversation.users:id,name,email,avatar_path', 'creator:id,name,avatar_path', 'participantRows.user:id,name,avatar_path']);

            return response()->json([
                'call' => $this->serialize($call),
                'token' => $this->liveKit->createToken($user, $call->room_name),
                'livekit_url' => $this->liveKit->wsUrl(),
                'already_active' => true,
            ]);
        }

        if ($call->status !== Call::STATUS_RINGING) {
            throw new ConflictHttpException('Звонок нельзя принять.');
        }

        $call->update([
            'status' => Call::STATUS_ACTIVE,
            'started_at' => $call->started_at ?? now(),
        ]);
        $call->markJoined($user);
        $call->touch();
        $call->load(['conversation.users:id,name,email,avatar_path', 'creator:id,name,avatar_path', 'participantRows.user:id,name,avatar_path']);

        $payload = $this->serialize($call);
        SafeBroadcast::now(new CallAccepted($payload, $this->broadcastChannels($call)));

        return response()->json([
            'call' => $payload,
            'token' => $this->liveKit->createToken($user, $call->room_name),
            'livekit_url' => $this->liveKit->wsUrl(),
        ]);
    }

    public function join(Request $request, Call $call): JsonResponse
    {
        $user = $request->user();
        $this->assertCallAccess($call, $user);
        Call::expireStaleForConversation((int) $call->conversation_id);
        $call->refresh();

        if (! $call->isOpen()) {
            throw new ConflictHttpException('Звонок уже завершён.');
        }

        // 1:1 ringing: callee accepts; caller resumes with a fresh token
        if ($call->conversation->isDirect() && $call->status === Call::STATUS_RINGING) {
            if ((int) $call->created_by === (int) $user->id) {
                $call->markJoined($user);
                $call->touch();
                $call->load(['conversation.users:id,name,email,avatar_path', 'creator:id,name,avatar_path', 'participantRows.user:id,name,avatar_path']);

                return response()->json([
                    'call' => $this->serialize($call),
                    'token' => $this->liveKit->createToken($user, $call->room_name),
                    'livekit_url' => $this->liveKit->wsUrl(),
                    'resumed' => true,
                ]);
            }

            return $this->accept($request, $call);
        }

        if ($call->status === Call::STATUS_RINGING) {
            $call->update([
                'status' => Call::STATUS_ACTIVE,
                'started_at' => $call->started_at ?? now(),
            ]);
        }

        $call->markJoined($user);
        $call->touch();
        $call->load(['conversation.users:id,name,email,avatar_path', 'creator:id,name,avatar_path', 'participantRows.user:id,name,avatar_path']);

        $payload = $this->serialize($call);
        SafeBroadcast::now(new CallUpdated($payload, $this->broadcastChannels($call)));

        return response()->json([
            'call' => $payload,
            'token' => $this->liveKit->createToken($user, $call->room_name),
            'livekit_url' => $this->liveKit->wsUrl(),
        ]);
    }

    public function reject(Request $request, Call $call): JsonResponse
    {
        $user = $request->user();
        $this->assertCallAccess($call, $user);

        if ($call->status !== Call::STATUS_RINGING) {
            throw new ConflictHttpException('Звонок нельзя отклонить.');
        }

        if ((int) $call->created_by === (int) $user->id) {
            return $this->end($request, $call);
        }

        $call->update([
            'status' => Call::STATUS_REJECTED,
            'ended_at' => now(),
        ]);
        $call->load(['conversation.users:id,name,email,avatar_path', 'creator:id,name,avatar_path', 'participantRows.user:id,name,avatar_path']);

        $payload = $this->serialize($call);
        SafeBroadcast::now(new CallRejected($payload, $this->broadcastChannels($call)));

        return response()->json(['call' => $payload]);
    }

    public function end(Request $request, Call $call): JsonResponse
    {
        $user = $request->user();
        $this->assertCallAccess($call, $user);

        if (! $call->isOpen()) {
            return response()->json(['call' => $this->serialize($call->loadMissing([
                'conversation.users:id,name,email,avatar_path',
                'creator:id,name,avatar_path',
                'participantRows.user:id,name,avatar_path',
            ]))]);
        }

        $call->update([
            'status' => Call::STATUS_ENDED,
            'ended_at' => now(),
        ]);

        foreach ($call->participantRows()->whereNull('left_at')->get() as $row) {
            $row->update(['left_at' => now()]);
        }

        $call->load(['conversation.users:id,name,email,avatar_path', 'creator:id,name,avatar_path', 'participantRows.user:id,name,avatar_path']);
        $payload = $this->serialize($call);
        SafeBroadcast::now(new CallEnded($payload, $this->broadcastChannels($call)));

        return response()->json(['call' => $payload]);
    }

    public function token(Request $request, Call $call): JsonResponse
    {
        $user = $request->user();
        $this->assertCallAccess($call, $user);

        if (! $call->isOpen()) {
            throw new HttpException(422, 'Звонок не активен.');
        }

        $isCreatorWaiting = $call->status === Call::STATUS_RINGING
            && (int) $call->created_by === (int) $user->id;

        $isActiveParticipant = $call->status === Call::STATUS_ACTIVE
            && $call->participantRows()
                ->where('user_id', $user->id)
                ->whereNull('left_at')
                ->exists();

        if (! $isCreatorWaiting && ! $isActiveParticipant) {
            throw new AccessDeniedHttpException('Сначала присоединитесь к звонку.');
        }

        return response()->json([
            'token' => $this->liveKit->createToken($user, $call->room_name),
            'livekit_url' => $this->liveKit->wsUrl(),
            'call' => $this->serialize($call->loadMissing([
                'conversation.users:id,name,email,avatar_path',
                'creator:id,name,avatar_path',
                'participantRows.user:id,name,avatar_path',
            ])),
        ]);
    }

    public function active(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        ConversationController::assertParticipant($conversation, $user);
        Call::expireStaleForConversation((int) $conversation->id);

        $call = Call::query()
            ->where('conversation_id', $conversation->id)
            ->whereIn('status', [Call::STATUS_RINGING, Call::STATUS_ACTIVE])
            ->with(['conversation.users:id,name,email,avatar_path', 'creator:id,name,avatar_path', 'participantRows.user:id,name,avatar_path'])
            ->latest('id')
            ->first();

        // Heartbeat: пока кто-то открыл чат со звонком — не считаем active брошенным
        if ($call && $call->status === Call::STATUS_ACTIVE) {
            $call->touch();
        }

        return response()->json([
            'call' => $call ? $this->serialize($call) : null,
        ]);
    }

    private function assertCallAccess(Call $call, User $user): void
    {
        $call->loadMissing('conversation');
        ConversationController::assertParticipant($call->conversation, $user);
    }

    /**
     * @return list<string>
     */
    private function broadcastChannels(Call $call, ?int $excludeUserId = null): array
    {
        $call->loadMissing('conversation.users');
        $conversation = $call->conversation;
        $channels = [];

        if ($conversation->isGroup()) {
            $channels[] = 'conversation.'.$conversation->id;
        }

        foreach ($conversation->users as $member) {
            if ($excludeUserId !== null && (int) $member->id === $excludeUserId) {
                continue;
            }
            $channels[] = 'user.'.$member->id;
        }

        return array_values(array_unique($channels));
    }

    private function serialize(Call $call): array
    {
        $call->loadMissing([
            'conversation:id,type,title',
            'creator:id,name,avatar_path',
            'participantRows.user:id,name,avatar_path',
        ]);

        $activeParticipants = $call->participantRows
            ->filter(fn ($row) => $row->left_at === null && $row->user)
            ->map(fn ($row) => [
                'id' => $row->user->id,
                'name' => $row->user->name,
                'avatar_url' => $row->user->avatar_url,
            ])
            ->values()
            ->all();

        return [
            'id' => (int) $call->id,
            'conversation_id' => (int) $call->conversation_id,
            'conversation_type' => $call->conversation->type ?? 'direct',
            'conversation_title' => $call->conversation->title,
            'created_by' => (int) $call->created_by,
            'creator' => $call->creator ? [
                'id' => (int) $call->creator->id,
                'name' => $call->creator->name,
                'avatar_url' => $call->creator->avatar_url,
            ] : null,
            'room_name' => $call->room_name,
            'media_type' => $call->media_type,
            'status' => $call->status,
            'started_at' => $call->started_at,
            'ended_at' => $call->ended_at,
            'participants' => $activeParticipants,
        ];
    }
}
