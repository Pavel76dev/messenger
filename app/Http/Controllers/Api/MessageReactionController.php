<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageReactionUpdated;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Support\SafeBroadcast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MessageReactionController extends Controller
{
    public function toggle(Request $request, Message $message): JsonResponse
    {
        ConversationController::assertParticipant($message->conversation, $request->user());

        $allowedReactions = config('message_reactions.allowed', []);

        $data = $request->validate([
            'reaction_key' => ['required', 'string', 'max:32', Rule::in(array_keys($allowedReactions))],
        ]);

        $reactionKey = $data['reaction_key'];
        $userId = $request->user()->id;

        $existing = $message->reactions()
            ->where('user_id', $userId)
            ->where('reaction_key', $reactionKey)
            ->first();

        $added = false;
        if ($existing) {
            $existing->delete();
        } else {
            $message->reactions()->create([
                'user_id' => $userId,
                'reaction_key' => $reactionKey,
            ]);
            $added = true;
        }

        $message->load(['user:id,name,avatar_path', 'attachments', 'reactions']);

        $payload = app(MessageController::class)->serializeForApi($message, $userId);
        SafeBroadcast::toOthers(new MessageReactionUpdated(
            (int) $message->conversation_id,
            $payload
        ));

        return response()->json([
            'message' => $payload,
            'reaction_added' => $added,
        ]);
    }
}
