<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAttachment extends Model
{
    public const EXTRACT_IDLE = 'idle';

    public const EXTRACT_PENDING = 'pending';

    public const EXTRACT_DONE = 'done';

    public const EXTRACT_ERROR = 'error';

    protected $fillable = [
        'message_id',
        'path',
        'original_name',
        'mime',
        'size',
        'extract_status',
        'extracted_text',
        'extract_error',
    ];

    protected $attributes = [
        'extract_status' => self::EXTRACT_IDLE,
    ];

    protected $appends = [
        'url',
    ];

    protected $hidden = [
        'path',
        'extracted_text',
        'extract_error',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function getUrlAttribute(): string
    {
        return '/storage/'.ltrim($this->path, '/');
    }
}
