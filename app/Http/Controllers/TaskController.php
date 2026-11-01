<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\OwnsStartupResources;
use App\Models\Goal;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    use OwnsStartupResources;

    private function storeRules(): array
    {
        return [
            'startup_id' => 'required|integer',
            'goal_id' => 'nullable|integer',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'status' => 'sometimes|in:pending,in_progress,completed',
            'priority' => 'sometimes|in:low,medium,high',
            'due_date' => 'nullable|date',
        ];
    }

    private function updateRules(): array
    {
        // startup_id is intentionally omitted: a task must never be moved to
        // another startup. goal_id is allowed to change but is re-scoped to
        // the task's own startup in update() below, so it can never point at
        // another founder's goal.
        return [
            'goal_id' => 'nullable|integer',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:5000',
            'status' => 'sometimes|in:pending,in_progress,completed',
            'priority' => 'sometimes|in:low,medium,high',
            'due_date' => 'nullable|date',
        ];
    }

    public function index(Request $r)
    {
        $q = Task::whereHas('startup.founder', fn ($q) => $q->where('user_id', $r->user()->id));
        if ($r->status) {
            $q->where('status', $r->status);
        }

        // Only allow sorting by a known column to prevent arbitrary-column
        // ordering / error probing.
        $sort = $r->get('sort', 'due_date');
        $q->orderBy(in_array($sort, ['due_date', 'priority', 'status', 'created_at'], true) ? $sort : 'due_date');

        return $q->get();
    }

    public function store(Request $r)
    {
        $d = $r->validate($this->storeRules());
        $s = $this->startup($r, $d['startup_id']);

        // A goal_id, when provided, must belong to the same startup.
        if (!empty($d['goal_id'])) {
            $d['goal_id'] = Goal::where('id', $d['goal_id'])
                ->where('startup_id', $s->id)
                ->value('id');
        }

        return response()->json($s->tasks()->create($d), 201);
    }

    public function show(Request $r, Task $task)
    {
        return $this->owns($r, $task);
    }

    public function update(Request $r, Task $task)
    {
        $this->owns($r, $task);
        $d = $r->validate($this->updateRules());

        // Reassigning a task to a goal owned by another startup is forbidden:
        // resolve the id only if the goal belongs to this task's startup.
        if (array_key_exists('goal_id', $d) && $d['goal_id'] !== null) {
            $d['goal_id'] = Goal::where('id', $d['goal_id'])
                ->where('startup_id', $task->startup_id)
                ->value('id');
        }

        $task->update($d);

        return $task->fresh();
    }

    public function destroy(Request $r, Task $task)
    {
        $this->owns($r, $task)->delete();

        return response()->noContent();
    }
}
