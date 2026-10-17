<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AudioClip extends Model
{
    public const TRANSCRIPT_IDLE = 'idle';

    public const TRANSCRIPT_PENDING = 'pending';

    public const TRANSCRIPT_DONE = 'done';

    public const TRANSCRIPT_ERROR = 'error';

    public const TASKS_EXTRACT_IDLE = 'idle';

    public const TASKS_EXTRACT_PENDING = 'pending';

    public const TASKS_EXTRACT_DONE = 'done';

    public const TASKS_EXTRACT_EMPTY = 'empty';

    public const TASKS_EXTRACT_ERROR = 'error';

    protected $fillable = [
        'user_id',
        'path',
        'mime',
        'size',
        'duration_ms',
        'transcript_status',
        'transcript_text',
        'transcript_error',
        'tasks_extract_status',
        'tasks_extract_error',
    ];

    protected $hidden = [
        'path',
    ];

    protected $attributes = [
        'transcript_status' => self::TRANSCRIPT_IDLE,
        'tasks_extract_status' => self::TASKS_EXTRACT_IDLE,
    ];

    protected $casts = [
        'size' => 'integer',
        'duration_ms' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function taskLists(): HasMany
    {
        return $this->hasMany(TaskList::class)->orderByDesc('created_at');
    }

    public function getUrlAttribute(): string
    {
        return '/storage/'.ltrim($this->path, '/');
    }

    public function toApiArray(): array
    {
        $lists = $this->relationLoaded('taskLists')
            ? $this->taskLists
            : $this->taskLists()->with('items')->get();

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
            'tasks_extract_status' => $this->tasks_extract_status ?: self::TASKS_EXTRACT_IDLE,
            'tasks_extract_error' => $this->tasks_extract_error,
            'task_lists' => $lists->map(fn (TaskList $list) => $list->toApiArray())->values()->all(),
        ];
    }
}
