<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\OwnsStartupResources;
use App\Models\Activity;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    use OwnsStartupResources;

    private function storeRules(): array
    {
        return [
            'startup_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'type' => 'required|in:customer_interview,meeting,research,product_development,decision,experiment,milestone,note',
            'occurred_at' => 'required|date',
        ];
    }

    private function updateRules(): array
    {
        // startup_id is intentionally omitted: an activity must never be moved
        // to another startup. Ownership of the existing row is verified via owns().
        return [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:5000',
            'type' => 'sometimes|in:customer_interview,meeting,research,product_development,decision,experiment,milestone,note',
            'occurred_at' => 'sometimes|date',
        ];
    }

    public function index(Request $r)
    {
        return Activity::whereHas('startup.founder', fn ($q) => $q->where('user_id', $r->user()->id))
            ->latest('occurred_at')
            ->get();
    }

    public function store(Request $r)
    {
        $d = $r->validate($this->storeRules());
        $s = $this->startup($r, $d['startup_id']);

        return response()->json($s->activities()->create($d), 201);
    }

    public function show(Request $r, Activity $activity)
    {
        return $this->owns($r, $activity);
    }

    public function update(Request $r, Activity $activity)
    {
        $this->owns($r, $activity)->update($r->validate($this->updateRules()));

        return $activity->fresh();
    }

    public function destroy(Request $r, Activity $activity)
    {
        $this->owns($r, $activity)->delete();

        return response()->noContent();
    }
}
