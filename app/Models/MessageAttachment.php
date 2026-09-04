<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAttachment extends Model
{
    protected $fillable = [
        'message_id',
        'path',
        'original_name',
        'mime',
        'size',
    ];

    protected $appends = [
        'url',
    ];

    protected $hidden = [
        'path',
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
