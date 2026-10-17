<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskList extends Model
{
    public const SOURCE_AUDIO_CLIP_TRANSCRIPT = 'audio_clip_transcript';

    protected $fillable = [
        'user_id',
        'audio_clip_id',
        'title',
        'source',
    ];

    protected $attributes = [
        'source' => self::SOURCE_AUDIO_CLIP_TRANSCRIPT,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function audioClip(): BelongsTo
    {
        return $this->belongsTo(AudioClip::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TaskItem::class)->orderBy('position')->orderBy('id');
    }

    public function toApiArray(): array
    {
        $items = $this->relationLoaded('items')
            ? $this->items
            : $this->items()->get();

        return [
            'id' => $this->id,
            'audio_clip_id' => (int) $this->audio_clip_id,
            'title' => $this->title,
            'source' => $this->source,
            'created_at' => $this->created_at,
            'items' => $items->map(fn (TaskItem $item) => $item->toApiArray())->values()->all(),
        ];
    }
}
