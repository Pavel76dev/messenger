<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Call extends Model
{
    public const MEDIA_AUDIO = 'audio';

    public const MEDIA_VIDEO = 'video';

    public const STATUS_RINGING = 'ringing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'conversation_id',
        'created_by',
        'room_name',
        'media_type',
        'status',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'call_participants')
            ->withPivot(['joined_at', 'left_at'])
            ->withTimestamps();
    }

    public function participantRows(): HasMany
    {
        return $this->hasMany(CallParticipant::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_RINGING, self::STATUS_ACTIVE], true);
    }

    /**
     * Close abandoned ringing/active calls so UI is not stuck.
     */
    public static function expireStaleForConversation(int $conversationId): int
    {
        $ringingExpired = static::query()
            ->where('conversation_id', $conversationId)
            ->where('status', self::STATUS_RINGING)
            ->where('created_at', '<', now()->subSeconds(90))
            ->update([
                'status' => self::STATUS_ENDED,
                'ended_at' => now(),
            ]);

        $activeExpired = static::query()
            ->where('conversation_id', $conversationId)
            ->where('status', self::STATUS_ACTIVE)
            ->where('updated_at', '<', now()->subHours(3))
            ->update([
                'status' => self::STATUS_ENDED,
                'ended_at' => now(),
            ]);

        return (int) $ringingExpired + (int) $activeExpired;
    }

    public function markJoined(User $user): void
    {
        $existing = CallParticipant::query()
            ->where('call_id', $this->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            $existing->update([
                'joined_at' => $existing->joined_at ?? now(),
                'left_at' => null,
            ]);

            return;
        }

        CallParticipant::query()->create([
            'call_id' => $this->id,
            'user_id' => $user->id,
            'joined_at' => now(),
        ]);
    }

    public function markLeft(User $user): void
    {
        CallParticipant::query()
            ->where('call_id', $this->id)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->update(['left_at' => now()]);
    }
}
