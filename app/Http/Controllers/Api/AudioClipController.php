<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AudioClip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AudioClipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
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
        ]);

        return response()->json($clip->toApiArray(), 201);
    }

    public function destroy(Request $request, AudioClip $audioClip): JsonResponse
    {
        if ((int) $audioClip->user_id !== (int) $request->user()->id) {
            abort(403, 'Нет доступа к этой записи.');
        }

        Storage::disk('public')->delete($audioClip->path);
        $audioClip->delete();

        return response()->json(['ok' => true]);
    }
}
