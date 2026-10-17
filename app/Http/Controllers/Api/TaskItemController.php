<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaskItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskItemController extends Controller
{
    public function update(Request $request, TaskItem $taskItem): JsonResponse
    {
        $taskItem->loadMissing('taskList');
        if (! $taskItem->taskList
            || (int) $taskItem->taskList->user_id !== (int) $request->user()->id) {
            abort(403, 'Нет доступа к этой задаче.');
        }

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in([TaskItem::STATUS_OPEN, TaskItem::STATUS_DONE])],
        ]);

        $taskItem->status = $data['status'];
        $taskItem->save();

        return response()->json($taskItem->toApiArray());
    }
}
