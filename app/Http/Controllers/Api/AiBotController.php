<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiBot;
use Illuminate\Http\JsonResponse;

class AiBotController extends Controller
{
    public function show(AiBot $aiBot): JsonResponse
    {
        $bot = $aiBot->ensureUser();

        return response()->json([
            'id' => $bot->id,
            'name' => $bot->name,
            'email' => $bot->email,
            'avatar_url' => $bot->avatar_url,
        ]);
    }
}
