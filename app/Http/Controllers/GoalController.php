<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\OwnsStartupResources;
use App\Models\Goal;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    use OwnsStartupResources;

    private function storeRules(): array
    {
        return [
            'startup_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'status' => 'sometimes|in:active,completed,paused',
            'progress' => 'sometimes|integer|min:0|max:100',
            'deadline' => 'nullable|date',
        ];
    }

    private function updateRules(): array
    {
        // startup_id is intentionally omitted: a goal must never be moved to
        // another startup. Ownership of the existing row is verified via owns().
        return [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:5000',
            'status' => 'sometimes|in:active,completed,paused',
            'progress' => 'sometimes|integer|min:0|max:100',
            'deadline' => 'nullable|date',
        ];
    }

    public function index(Request $r)
    {
        return Goal::whereHas('startup.founder', fn ($q) => $q->where('user_id', $r->user()->id))
            ->latest()
            ->get();
    }

    public function store(Request $r)
    {
        $d = $r->validate($this->storeRules());
        $s = $this->startup($r, $d['startup_id']);

        return response()->json($s->goals()->create($d), 201);
    }

    public function show(Request $r, Goal $goal)
    {
        return $this->owns($r, $goal);
    }

    public function update(Request $r, Goal $goal)
    {
        $this->owns($r, $goal)->update($r->validate($this->updateRules()));

        return $goal->fresh();
    }

    public function destroy(Request $r, Goal $goal)
    {
        $this->owns($r, $goal)->delete();

        return response()->noContent();
    }
}
