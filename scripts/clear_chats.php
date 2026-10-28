<?php

/**
 * Очистка чатов для теста «с чистого листа».
 * Пользователи, настройки, аудио-библиотека — без изменений.
 * Диалоги с ассистентом пересоздаются при следующем GET /api/conversations.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Call;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageReaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

DB::transaction(function () {
    $attachmentPaths = MessageAttachment::query()->pluck('path')->all();

    MessageReaction::query()->delete();
    MessageAttachment::query()->delete();
    Message::query()->delete();

    // Звонки привязаны к диалогам
    if (Schema::hasTable('call_participants')) {
        DB::table('call_participants')->delete();
    }
    if (Schema::hasTable('calls')) {
        Call::query()->delete();
    }

    DB::table('conversation_user')->delete();
    Conversation::query()->delete();

    foreach ($attachmentPaths as $path) {
        if (is_string($path) && $path !== '') {
            Storage::disk('public')->delete($path);
        }
    }
});

echo "OK: chats cleared\n";
echo 'conversations='.Conversation::count()."\n";
echo 'messages='.Message::count()."\n";
echo 'attachments='.MessageAttachment::count()."\n";
echo 'reactions='.MessageReaction::count()."\n";
echo 'users kept='.\App\Models\User::count()."\n";
echo 'audio_clips kept='.\App\Models\AudioClip::count()."\n";
