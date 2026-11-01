<?php
namespace App\Services\AI;

use App\Models\Startup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Deterministic, data-grounded analysis of the founder's current state.
 * Every observation is derived from stored data - never invented by the AI.
 */
class FounderStateAnalyzer
{
    public const VALIDATION_STAGE = 'validation';
    public const VALIDATION_ACTIVITY_TYPES = ['customer_interview','experiment','research'];

    /**
     * @return array{
     *   progress: array, stagnation: array, neglected_areas: array,
     *   patterns: array, risks: array, opportunities: array, priorities: array
     * }
     */
    public function analyze(Startup $s): array
    {
        $now = Carbon::now();
        // Safety caps: a startup can accumulate a long history over time, but the
        // dashboard snapshot only needs a bounded, recent/representative slice.
        // These limits are well above typical MVP usage so analysis is unchanged
        // for normal startups while preventing unbounded memory on every load.
        $goals = $s->goals()->orderByDesc('progress')->limit(100)->get();
        $activeGoals = $goals->where('status', 'active');
        $tasks = $s->tasks()->latest()->limit(500)->get();
        $activities = $s->activities()->orderByDesc('occurred_at')->limit(500)->get();
        $memories = $s->memories()->where('superseded', false)->orderByDesc('importance')->limit(100)->get();

        $progress = $this->progress($activeGoals, $tasks, $activities);
        $stagnation = $this->stagnation($activeGoals, $activities);
        $neglected = $this->neglectedAreas($s, $activeGoals, $tasks, $activities);
        $patterns = $this->patterns($s, $activities, $memories);
        $risks = $this->risks($s, $activeGoals, $tasks, $activities);
        $opportunities = $this->opportunities($activeGoals, $tasks, $activities);
        $priorities = $this->priorities($risks, $neglected, $activeGoals);

        return [
            'progress' => $progress,
            'stagnation' => $stagnation,
            'neglected_areas' => $neglected,
            'patterns' => $patterns,
            'risks' => $risks,
            'opportunities' => $opportunities,
            'priorities' => $priorities,
        ];
    }

    private function progress(Collection $activeGoals, Collection $tasks, Collection $activities): array
    {
        $completedTasks = $tasks->where('status', 'completed')->count();
        $milestones = $activities->where('type', 'milestone');
        $recent = [];

        foreach ($activeGoals->where('progress', '>', 0)->take(3) as $g) {
            $recent[] = "Goal \"{$g->title}\" is at {$g->progress}% progress.";
        }
        if ($completedTasks) $recent[] = "{$completedTasks} task(s) completed.";
        foreach ($milestones->take(2) as $m) $recent[] = "Milestone logged: {$m->title}.";

        return ['summary' => $recent];
    }

    private function stagnation(Collection $activeGoals, Collection $activities): array
    {
        $out = [];
        foreach ($activeGoals->take(5) as $g) {
            if ($g->progress <= 0 && $g->updated_at && $g->updated_at->lt(Carbon::now()->subDays(7))) {
                $out[] = "Goal \"{$g->title}\" shows no progress movement recently.";
            }
        }
        if ($activities->isEmpty()) $out[] = 'No activities have been recorded yet.';
        return $out;
    }

    private function neglectedAreas(Startup $s, Collection $activeGoals, Collection $tasks, Collection $activities): array
    {
        $out = [];
        foreach ($activeGoals->take(5) as $g) {
            $hasActivity = $activities->contains(fn($a) => $s->id === $g->startup_id) && $g->updated_at && $g->updated_at->gte(Carbon::now()->subDays(14));
            if (!$hasActivity && $g->progress < 100) {
                $out[] = "Goal \"{$g->title}\" has had no recorded activity.";
            }
        }
        if ($s->stage === self::VALIDATION_STAGE) {
            $interviewCount = $activities->where('type', 'customer_interview')->count();
            if ($interviewCount === 0) $out[] = 'No customer interviews recorded despite being in the validation stage.';
        }
        foreach ($tasks->whereIn('status', ['pending', 'in_progress'])->where('priority', 'high')->take(3) as $t) {
            $out[] = "High-priority task \"{$t->title}\" is still open.";
        }
        return array_slice(array_values(array_unique($out)), 0, 5);
    }

    private function patterns(Startup $s, Collection $activities, Collection $memories): array
    {
        $out = [];
        $byType = $activities->countBy('type');
        $total = max(1, $activities->count());
        $dominate = $byType->filter(fn($c, $t) => $t !== 'note' && $c / $total >= 0.4);
        foreach ($dominate->take(2) as $type => $count) {
            $out[] = "Most activity is {$type} ({$count} recorded).";
        }
        foreach ($memories->where('category', 'customer')->take(2) as $m) {
            $out[] = "Customer note: {$m->content}";
        }
        return $out;
    }

    private function risks(Startup $s, Collection $activeGoals, Collection $tasks, Collection $activities): array
    {
        $out = [];
        $overdue = $tasks->where('status', '!=', 'completed')->whereNotNull('due_date')->filter(fn($t) => $t->due_date && $t->due_date->lt(Carbon::now()));
        foreach ($overdue->take(3) as $t) $out[] = "Task \"{$t->title}\" is overdue (due {$t->due_date->toDateString()}).";

        foreach ($activeGoals as $g) {
            if ($g->deadline && $g->deadline->lt(Carbon::now()) && $g->progress < 100) {
                $out[] = "Goal \"{$g->title}\" passed its deadline unfinished.";
            }
        }

        if ($activities->isEmpty()) $out[] = 'No recorded activity makes it hard to see momentum.';

        $lastActivity = $activities->first()?->occurred_at;
        if ($lastActivity && $lastActivity->lt(Carbon::now()->subDays(7)) && $s->stage !== 'idea') {
            $out[] = 'No activity recorded in the past 7 days — momentum appears to have stalled.';
        }

        return array_slice(array_values(array_unique($out)), 0, 5);
    }

    private function opportunities(Collection $activeGoals, Collection $tasks, Collection $activities): array
    {
        $out = [];
        foreach ($activeGoals->where('progress', '>=', 75)->where('progress', '<', 100)->take(2) as $g) {
            $out[] = "Goal \"{$g->title}\" is close to completion — push it over the line.";
        }
        $interviews = $activities->where('type', 'customer_interview');
        if ($interviews->count() >= 5) $out[] = 'You have reached 5 or more customer interviews; analyse the common themes.';
        return $out;
    }

    private function priorities(array $risks, array $neglected, Collection $activeGoals): array
    {
        $out = [];
        foreach (array_merge($risks, $neglected) as $item) {
            $out[] = ['item' => $item, 'priority' => 'high'];
        }
        $first = $activeGoals->first();
        if ($first) $out[] = ['item' => "Focus on active goal \"{$first->title}\".", 'priority' => 'medium'];
        return array_slice($out, 0, 4);
    }
}
