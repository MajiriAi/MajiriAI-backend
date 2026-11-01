<?php
namespace App\Http\Controllers;

use App\Models\Startup;
use App\Services\AI\FounderStateAnalyzer;
use App\Services\AI\GoalHealthService;
use App\Services\AI\NextActionService;
use Illuminate\Http\Request;

/**
 * "Command center" dashboard. Fully deterministic - no AI calls on page load.
 * AI-powered insights/analysis are surfaced on demand elsewhere.
 */
class DashboardController extends Controller
{
    public function __invoke(
        Request $r,
        FounderStateAnalyzer $analyzer,
        GoalHealthService $goalHealth,
        NextActionService $nextActions
    ) {
        $s = Startup::whereHas('founder', fn($q) => $q->where('user_id', $r->user()->id))->latest()->firstOrFail();

        $activeGoals = $s->goals()->where('status', 'active')->orderByDesc('progress')->get();
        $state = $analyzer->analyze($s);

        return [
            'startup' => $s,
            'founder_status' => [
                'stage' => $s->stage,
                'summary' => $this->founderSummary($state),
                'priorities' => $state['priorities'],
            ],
            'current_focus' => $state['priorities'][0]['item'] ?? null,
            'goal_health' => $goalHealth->assessAll($activeGoals),
            'recommended_actions' => $nextActions->forStartup($s),
            'ai_insight' => $s->aiInsights()->latest()->first(),
            'current_goal' => $activeGoals->first(),
            'todays_tasks' => $s->tasks()->whereIn('status', ['pending', 'in_progress'])->orderByDesc('priority')->limit(5)->get(),
            'recent_activities' => $s->activities()->latest('occurred_at')->limit(5)->get(),
            'recent_progress' => $state['progress']['summary'],
            'state_analysis' => $state,
        ];
    }

    private function founderSummary(array $state): string
    {
        $bits = [];
        $risks = $state['risks'];
        $neglected = $state['neglected_areas'];
        if ($risks) $bits[] = ucfirst($risks[0]);
        if ($neglected) $bits[] = ucfirst($neglected[0]);
        if (!$bits) $bits[] = 'Things appear to be progressing.';
        return implode(' ', $bits);
    }
}
