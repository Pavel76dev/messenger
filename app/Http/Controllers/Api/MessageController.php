<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MessageController extends Controller
{
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        ConversationController::assertParticipant($conversation, $request->user());

        $afterId = $request->query('after_id');

        $query = $conversation->messages()
            ->with(['user:id,name,avatar_path', 'attachments'])
            ->orderBy('id');

        if ($afterId !== null && $afterId !== '') {
            $query->where('id', '>', (int) $afterId);
        }

        $messages = $query->limit(100)->get()->map(fn (Message $message) => $this->serialize($message));

        return response()->json($messages);
    }

    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        ConversationController::assertParticipant($conversation, $request->user());

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'files' => ['nullable', 'array', 'max:5'],
            'files.*' => ['file', 'max:10240'],
        ]);

        $body = isset($data['body']) ? trim($data['body']) : '';
        $files = $request->file('files', []);

        if ($files === null) {
            $files = [];
        }

        if (! is_array($files)) {
            $files = [$files];
        }

        $files = array_values(array_filter($files));

        if ($body === '' && count($files) === 0) {
            throw ValidationException::withMessages([
                'body' => ['Нужен текст или хотя бы один файл.'],
            ]);
        }

        if (count($files) > 5) {
            throw ValidationException::withMessages([
                'files' => ['Не больше 5 файлов на сообщение.'],
            ]);
        }

        $message = DB::transaction(function () use ($request, $conversation, $body, $files) {
            $message = $conversation->messages()->create([
                'user_id' => $request->user()->id,
                'body' => $body === '' ? null : $body,
            ]);

            foreach ($files as $file) {
                $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
                $safeName = $safeName !== '' ? $safeName : 'file';
                $extension = $file->getClientOriginalExtension();
                $filename = Str::uuid()->toString().'_'.$safeName.($extension ? '.'.$extension : '');
                $path = $file->storeAs(
                    "attachments/{$conversation->id}",
                    $filename,
                    'public'
                );

                $message->attachments()->create([
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime' => $file->getClientMimeType(),
                    'size' => $file->getSize() ?: 0,
                ]);
            }

            $conversation->touch();

            return $message;
        });

        $message->load(['user:id,name,avatar_path', 'attachments']);

        return response()->json($this->serialize($message), 201);
    }

    private function serialize(Message $message): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'user_id' => $message->user_id,
            'body' => $message->body,
            'created_at' => $message->created_at,
            'user' => $message->user ? [
                'id' => $message->user->id,
                'name' => $message->user->name,
                'avatar_url' => $message->user->avatar_url,
            ] : null,
            'attachments' => $message->attachments->map(fn (MessageAttachment $attachment) => [
                'id' => $attachment->id,
                'url' => $attachment->url,
                'original_name' => $attachment->original_name,
                'mime' => $attachment->mime,
                'size' => $attachment->size,
            ])->values()->all(),
        ];
    }
}
