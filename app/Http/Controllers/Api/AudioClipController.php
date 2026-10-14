<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageCreated;
use App\Http\Controllers\Controller;
use App\Models\AudioClip;
use App\Models\Conversation;
use App\Services\AsrBackground;
use App\Support\SafeBroadcast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AudioClipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->failStalePendingForUser((int) $request->user()->id);

        $clips = AudioClip::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (AudioClip $clip) => $clip->toApiArray())
            ->values();

        return response()->json($clips);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimetypes:audio/webm,audio/ogg,audio/mp4,audio/mpeg,audio/wav,audio/x-wav,audio/mp3,video/webm',
            ],
            'duration_ms' => ['nullable', 'integer', 'min:0', 'max:600000'],
        ]);

        $user = $request->user();
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'webm');
        if ($extension === '' || strlen($extension) > 8) {
            $extension = 'webm';
        }

        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs("audio_clips/{$user->id}", $filename, 'public');

        $clip = AudioClip::query()->create([
            'user_id' => $user->id,
            'path' => $path,
            'mime' => $file->getClientMimeType() ?: 'audio/webm',
            'size' => $file->getSize() ?: 0,
            'duration_ms' => $data['duration_ms'] ?? null,
            'transcript_status' => AudioClip::TRANSCRIPT_IDLE,
        ]);

        return response()->json($clip->toApiArray(), 201);
    }

    public function destroy(Request $request, AudioClip $audioClip): JsonResponse
    {
        $this->assertOwner($request, $audioClip);

        Storage::disk('public')->delete($audioClip->path);
        $audioClip->delete();

        return response()->json(['ok' => true]);
    }

    public function transcribe(Request $request, AudioClip $audioClip, AsrBackground $background): JsonResponse
    {
        $this->assertOwner($request, $audioClip);

        if ($audioClip->transcript_status === AudioClip::TRANSCRIPT_PENDING
            && ! $this->isStalePending($audioClip)) {
            return response()->json($audioClip->toApiArray());
        }

        if (! Storage::disk('public')->exists($audioClip->path)) {
            throw ValidationException::withMessages([
                'file' => ['Файл записи не найден на сервере.'],
            ]);
        }

        $audioClip->transcript_status = AudioClip::TRANSCRIPT_PENDING;
        $audioClip->transcript_error = null;
        $audioClip->transcript_text = null;
        $audioClip->save();

        // Отдельный PHP-процесс — HTTP сразу отдаёт 202, без белого экрана OpenServer.
        $background->startTranscribe((int) $audioClip->id);

        return response()->json($audioClip->fresh()->toApiArray(), 202);
    }

    public function send(Request $request, AudioClip $audioClip): JsonResponse
    {
        $this->assertOwner($request, $audioClip);

        $data = $request->validate([
            'conversation_id' => ['required', 'integer', 'exists:conversations,id'],
        ]);

        $conversation = Conversation::query()->findOrFail($data['conversation_id']);
        ConversationController::assertParticipant($conversation, $request->user());

        if (! Storage::disk('public')->exists($audioClip->path)) {
            throw ValidationException::withMessages([
                'file' => ['Файл записи не найден на сервере.'],
            ]);
        }

        $extension = pathinfo($audioClip->path, PATHINFO_EXTENSION) ?: 'webm';
        $originalName = 'audio-clip-'.$audioClip->id.'.'.$extension;
        $safeName = 'audio-clip';
        $filename = Str::uuid()->toString().'_'.$safeName.($extension ? '.'.$extension : '');

        $message = DB::transaction(function () use ($request, $conversation, $audioClip, $filename, $originalName) {
            $destRelative = "attachments/{$conversation->id}/{$filename}";
            Storage::disk('public')->copy($audioClip->path, $destRelative);

            $message = $conversation->messages()->create([
                'user_id' => $request->user()->id,
                'body' => null,
            ]);

            $message->attachments()->create([
                'path' => $destRelative,
                'original_name' => $originalName,
                'mime' => $audioClip->mime ?: 'audio/webm',
                'size' => (int) ($audioClip->size ?: Storage::disk('public')->size($destRelative)),
            ]);

            $conversation->touch();

            return $message;
        });

        $message->load(['user:id,name,avatar_path', 'attachments', 'reactions']);
        $payload = app(MessageController::class)->serializeForApi($message, $request->user()->id);
        SafeBroadcast::toOthers(new MessageCreated((int) $conversation->id, $payload));

        return response()->json([
            'message' => $payload,
            'clip' => $audioClip->toApiArray(),
        ], 201);
    }

    private function assertOwner(Request $request, AudioClip $audioClip): void
    {
        if ((int) $audioClip->user_id !== (int) $request->user()->id) {
            abort(403, 'Нет доступа к этой записи.');
        }
    }

    private function isStalePending(AudioClip $clip): bool
    {
        if ($clip->transcript_status !== AudioClip::TRANSCRIPT_PENDING) {
            return false;
        }

        $updated = $clip->updated_at;
        if (! $updated) {
            return true;
        }

        return $updated->lt(now()->subMinutes(3));
    }

    private function failStalePendingForUser(int $userId): void
    {
        AudioClip::query()
            ->where('user_id', $userId)
            ->where('transcript_status', AudioClip::TRANSCRIPT_PENDING)
            ->where('updated_at', '<', now()->subMinutes(3))
            ->update([
                'transcript_status' => AudioClip::TRANSCRIPT_ERROR,
                'transcript_error' => 'Транскрибация прервана или зависла. Нажмите ещё раз.',
            ]);
    }
}
