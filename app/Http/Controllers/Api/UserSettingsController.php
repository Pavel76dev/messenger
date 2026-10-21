<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSettingsController extends Controller
{
    public function show(Request $request, UserSettings $settings): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'definitions' => $settings->definitions(),
            'values' => $settings->effectiveValues($user),
            'overridden' => $settings->overriddenFlags($user),
        ]);
    }

    public function update(Request $request, UserSettings $settings): JsonResponse
    {
        $data = $request->validate([
            'values' => ['required', 'array'],
        ]);

        $settings->update($request->user(), $data['values']);

        return $this->show($request, $settings);
    }
}
