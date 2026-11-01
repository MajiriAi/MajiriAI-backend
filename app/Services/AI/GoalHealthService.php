<?php
namespace App\Services\AI;

use App\Models\Goal;
use Illuminate\Support\Carbon;

/**
 * Deterministic health rating for goals. Fully grounded in stored data -
 * no AI involved, so it is cheap, repeatable and testable.
 */
class GoalHealthService
{
    public const STALLED_AFTER_DAYS = 14;
    public const AT_RISK_INACTIVITY_DAYS = 7;
    public const DEADLINE_SOON_DAYS = 7;

    /**
     * @return array{status:string, reason:string, last_activity_at:string|null}
     */
    public function assess(Goal $goal): array
    {
        if ((int)$goal->progress >= 100) {
            return ['status' => 'completed', 'reason' => 'This goal has reached full progress.', 'last_activity_at' => $this->lastActivityAt($goal)];
        }

        $now = Carbon::now();
        $deadline = $goal->deadline;

        if ($deadline && $deadline->lt($now) && $goal->progress < 100) {
            return ['status' => 'at_risk', 'reason' => 'The deadline has passed and the goal is not yet complete.', 'last_activity_at' => $this->lastActivityAt($goal)];
        }

        if ($deadline && $deadline->diffInDays($now, false) <= self::DEADLINE_SOON_DAYS && $deadline->gte($now)) {
            return ['status' => 'at_risk', 'reason' => 'The deadline is approaching and the goal is not yet complete.', 'last_activity_at' => $this->lastActivityAt($goal)];
        }

        $lastActivity = $this->lastActivityTimestamp($goal);
        $inactiveDays = $lastActivity ? $lastActivity->diffInDays($now, false) : null;

        if ($inactiveDays === null || $inactiveDays >= self::STALLED_AFTER_DAYS) {
            return ['status' => 'stalled', 'reason' => $inactiveDays === null
                ? 'No activity has been recorded for this goal.'
                : "No activity has been recorded for this goal in the last {$inactiveDays} days.", 'last_activity_at' => $this->lastActivityAt($goal)];
        }

        if ($inactiveDays >= self::AT_RISK_INACTIVITY_DAYS) {
            return ['status' => 'at_risk', 'reason' => "No activity has been recorded for this goal in the last {$inactiveDays} days.", 'last_activity_at' => $this->lastActivityAt($goal)];
        }

        return ['status' => 'healthy', 'reason' => 'The goal is being actively worked on.', 'last_activity_at' => $this->lastActivityAt($goal)];
    }

    public function assessAll(iterable $goals): array
    {
        // Eager-load tasks once to avoid an N+1 query per goal on the dashboard path.
        if ($goals instanceof \Illuminate\Database\Eloquent\Collection) {
            $goals->load('tasks');
        }

        $out = [];
        foreach ($goals as $goal) {
            $out[] = array_merge(['id' => $goal->id, 'title' => $goal->title], $this->assess($goal));
        }
        return $out;
    }

    private function lastActivityTimestamp(Goal $goal): ?Carbon
    {
        $candidates = [];
        if ($goal->updated_at) $candidates[] = Carbon::parse($goal->updated_at);
        // Uses the in-memory tasks relation (eager-loaded by assessAll) to avoid
        // an N+1 query per goal; lazy-loads a single query for a standalone assess().
        $task = $goal->tasks->sortByDesc('updated_at')->first();
        if ($task && $task->updated_at) $candidates[] = Carbon::parse($task->updated_at);

        if (!$candidates) return null;

        $latest = $candidates[0];
        foreach ($candidates as $c) {
            if ($c->greaterThan($latest)) $latest = $c;
        }
        return $latest;
    }

    private function lastActivityAt(Goal $goal): ?string
    {
        $ts = $this->lastActivityTimestamp($goal);
        return $ts ? $ts->toIso8601String() : null;
    }
}
