<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AudioClip extends Model
{
    public const TRANSCRIPT_IDLE = 'idle';

    public const TRANSCRIPT_PENDING = 'pending';

    public const TRANSCRIPT_DONE = 'done';

    public const TRANSCRIPT_ERROR = 'error';

    protected $fillable = [
        'user_id',
        'path',
        'mime',
        'size',
        'duration_ms',
        'transcript_status',
        'transcript_text',
        'transcript_error',
    ];

    protected $hidden = [
        'path',
    ];

    protected $attributes = [
        'transcript_status' => self::TRANSCRIPT_IDLE,
    ];

    protected $casts = [
        'size' => 'integer',
        'duration_ms' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getUrlAttribute(): string
    {
        return '/storage/'.ltrim($this->path, '/');
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'mime' => $this->mime,
            'size' => (int) $this->size,
            'duration_ms' => $this->duration_ms,
            'created_at' => $this->created_at,
            'transcript_status' => $this->transcript_status ?: self::TRANSCRIPT_IDLE,
            'transcript_text' => $this->transcript_text,
            'transcript_error' => $this->transcript_error,
        ];
    }
}
