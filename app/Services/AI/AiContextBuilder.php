<?php
namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\Startup;

class AiContextBuilder
{
    public function __construct(
        private FounderStateAnalyzer $analyzer,
        private GoalHealthService $goalHealth
    ) {}

    /**
     * Build a flat, deterministic, bounded context string for the AI provider.
     * Sections mirror the structured array returned by buildStructured().
     */
    public function build(Startup $s, ?Conversation $c = null): string
    {
        $d = $this->buildStructured($s, $c);
        $parts = [];

        $parts[] = "FOUNDER\n" . json_encode($d['founder']);
        $parts[] = "STARTUP\n" . json_encode($d['startup']);
        $parts[] = "CURRENT STAGE\n" . $d['stage'];
        $parts[] = "ACTIVE GOALS\n" . json_encode($d['active_goals']);
        $parts[] = "GOAL HEALTH\n" . json_encode($d['goal_health']);
        $parts[] = "IMPORTANT TASKS\n" . json_encode($d['tasks']);
        $parts[] = "RECENT ACTIVITIES\n" . json_encode($d['activities']);
        $parts[] = "IMPORTANT MEMORIES\n" . json_encode($d['memories']);
        $parts[] = "RECENT INSIGHTS\n" . json_encode($d['insights']);
        $parts[] = "STATE ANALYSIS\n" . json_encode($d['state_analysis']);
        if (!empty($d['conversation'])) $parts[] = "RECENT CONVERSATION\n" . json_encode($d['conversation']);

        return implode("\n\n", $parts);
    }

    /**
     * Structured context as arrays - used by deterministic analysis and by the
     * plain text build() above. Bounded by AI config caps to avoid token bloat.
     */
    public function buildStructured(Startup $s, ?Conversation $c = null): array
    {
        $f = $s->founder;
        $goals = $s->goals()->where('status', 'active')->orderByDesc('progress')->limit(8)->get(['title','progress','deadline','status','id']);
        $tasks = $s->tasks()->whereIn('status', ['pending','in_progress'])
            ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->limit(config('ai.max_context_tasks', 12))->get(['title','priority','due_date','status']);
        $acts = $s->activities()->latest('occurred_at')->limit(config('ai.max_context_activities'))->get(['title','description','type','occurred_at']);
        $mem = $s->memories()->where('superseded', false)->orderByDesc('importance')->limit(config('ai.max_context_memories'))->get(['content','category','importance','confidence','source']);
        $insights = $s->aiInsights()->latest()->limit(config('ai.max_context_insights', 5))->get(['title','content','type','priority']);
        $messages = $c ? $c->messages()->latest()->limit(config('ai.max_context_messages'))->get()->reverse()->values() : collect();

        $state = $this->analyzer->analyze($s);
        $goalHealth = $this->goalHealth->assessAll($goals);

        return [
            'founder' => $f?->only(['background','skills','experience','interests','personal_goals','resources']),
            'startup' => $s->only(['name','description','problem','solution','target_customers','industry','stage','current_challenges']),
            'stage' => $s->stage,
            'active_goals' => $goals->toArray(),
            'goal_health' => $goalHealth,
            'tasks' => $tasks->toArray(),
            'activities' => $acts->toArray(),
            'memories' => $mem->toArray(),
            'insights' => $insights->toArray(),
            'state_analysis' => $state,
            'conversation' => $messages->map(fn($m) => $m->only(['role','content']))->toArray(),
        ];
    }
}
