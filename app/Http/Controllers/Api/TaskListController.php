<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaskList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskListController extends Controller
{
    public function destroy(Request $request, TaskList $taskList): JsonResponse
    {
        $this->assertOwner($request, $taskList);
        $taskList->delete();

        return response()->json(['ok' => true]);
    }

    private function assertOwner(Request $request, TaskList $taskList): void
    {
        if ((int) $taskList->user_id !== (int) $request->user()->id) {
            abort(403, 'Нет доступа к этому списку задач.');
        }
    }
}
