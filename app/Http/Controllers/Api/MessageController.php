<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        ConversationController::assertParticipant($conversation, $request->user());

        $afterId = $request->query('after_id');

        $query = $conversation->messages()
            ->with('user:id,name')
            ->orderBy('id');

        if ($afterId !== null && $afterId !== '') {
            $query->where('id', '>', (int) $afterId);
        }

        $messages = $query->limit(100)->get()->map(fn (Message $message) => [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'user_id' => $message->user_id,
            'body' => $message->body,
            'created_at' => $message->created_at,
            'user' => $message->user ? [
                'id' => $message->user->id,
                'name' => $message->user->name,
            ] : null,
        ]);

        return response()->json($messages);
    }

    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        ConversationController::assertParticipant($conversation, $request->user());

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message = $conversation->messages()->create([
            'user_id' => $request->user()->id,
            'body' => trim($data['body']),
        ]);

        $conversation->touch();
        $message->load('user:id,name');

        return response()->json([
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'user_id' => $message->user_id,
            'body' => $message->body,
            'created_at' => $message->created_at,
            'user' => [
                'id' => $message->user->id,
                'name' => $message->user->name,
            ],
        ], 201);
    }
}
