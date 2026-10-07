<?php

use App\Http\Controllers\Api\AiBotController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CallController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\MessageReactionController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/me', [ProfileController::class, 'update']);
    Route::post('/me/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::delete('/me/avatar', [ProfileController::class, 'deleteAvatar']);
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/ai-bot', [AiBotController::class, 'show']);
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations', [ConversationController::class, 'store']);
    Route::get('/conversations/{conversation}/messages', [MessageController::class, 'index']);
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store']);
    Route::post('/messages/{message}/reactions', [MessageReactionController::class, 'toggle']);

    Route::get('/conversations/{conversation}/calls/active', [CallController::class, 'active']);
    Route::post('/conversations/{conversation}/calls', [CallController::class, 'store']);
    Route::post('/calls/{call}/accept', [CallController::class, 'accept']);
    Route::post('/calls/{call}/join', [CallController::class, 'join']);
    Route::post('/calls/{call}/reject', [CallController::class, 'reject']);
    Route::post('/calls/{call}/end', [CallController::class, 'end']);
    Route::post('/calls/{call}/token', [CallController::class, 'token']);
});
