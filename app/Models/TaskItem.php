<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskItem extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_DONE = 'done';

    protected $fillable = [
        'task_list_id',
        'body',
        'position',
        'status',
    ];

    protected $attributes = [
        'status' => self::STATUS_OPEN,
        'position' => 0,
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function taskList(): BelongsTo
    {
        return $this->belongsTo(TaskList::class);
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'task_list_id' => (int) $this->task_list_id,
            'body' => $this->body,
            'position' => (int) $this->position,
            'status' => $this->status ?: self::STATUS_OPEN,
        ];
    }
}
