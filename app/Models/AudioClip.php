<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AudioClip extends Model
{
    protected $fillable = [
        'user_id',
        'path',
        'mime',
        'size',
        'duration_ms',
    ];

    protected $hidden = [
        'path',
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
        ];
    }
}
