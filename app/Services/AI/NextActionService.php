<?php
namespace App\Services\AI;

use App\Models\Startup;
use Illuminate\Support\Carbon;

/**
 * Produces 3-5 prioritized, grounded next actions.
 *
 * The baseline is deterministic and fully grounded in the database (overdue
 * tasks, goal health, stage gaps) - the AI is only asked to reason when we
 * need to enrich or add a data-driven suggestion, never to do database math.
 */
class NextActionService
{
    public const VALIDATION_TYPES = ['customer_interview', 'experiment', 'research'];

    public function __construct(
        private FounderStateAnalyzer $analyzer,
        private GoalHealthService $goalHealth,
        private ?AiProviderInterface $provider = null,
    ) {}

    /**
     * @return array<int,array{title:string,reason:string,priority:string,source:string}>
     */
    public function forStartup(Startup $s): array
    {
        $actions = $this->deterministic($s);
        $actions = $this->fillAndOrder($s, $actions);
        return array_slice($actions, 0, 5);
    }

    private function deterministic(Startup $s): array
    {
        $actions = [];
        $now = Carbon::now();

        $overdue = $s->tasks()->where('status', '!=', 'completed')
            ->whereNotNull('due_date')->orderBy('due_date')->get()
            ->filter(fn($t) => $t->due_date && $t->due_date->lt($now));
        foreach ($overdue->take(2) as $t) {
            $actions[] = ['title' => "Close out overdue task: {$t->title}", 'reason' => "This task is overdue (due {$t->due_date->toDateString()}) and blocking momentum.", 'priority' => 'high', 'source' => 'overdue_task'];
        }

        $goals = $s->goals()->where('status', 'active')->get();
        foreach ($goals->take(3) as $g) {
            $health = $this->goalHealth->assess($g);
            if ($health['status'] === 'at_risk' || $health['status'] === 'stalled') {
                $actions[] = [
                    'title' => "Re-engage with goal: {$g->title}",
                    'reason' => "This goal is {$health['status']}: {$health['reason']}",
                    'priority' => 'high',
                    'source' => 'goal_health',
                ];
            }
        }

        if ($s->stage === 'validation') {
            $interviews = $s->activities()->where('type', 'customer_interview')->count();
            if ($interviews === 0) {
                $actions[] = [
                    'title' => 'Conduct customer interviews',
                    'reason' => 'Your startup is in the validation stage, but no customer interviews are recorded yet.',
                    'priority' => 'high',
                    'source' => 'stage_gap',
                ];
            } elseif ($interviews < 5) {
                $actions[] = [
                    'title' => 'Continue customer interviews',
                    'reason' => "You have {$interviews} customer interview(s) recorded; reaching a few more will strengthen validation.",
                    'priority' => 'medium',
                    'source' => 'stage_gap',
                ];
            }
        }

        return $actions;
    }

    private function fillAndOrder(Startup $s, array $actions): array
    {
        // Deterministic ordering: high first, then medium/low.
        $order = ['high' => 1, 'medium' => 2, 'low' => 3];
        usort($actions, fn($a, $b) => ($order[$a['priority']] ?? 9) <=> ($order[$b['priority']] ?? 9));

        if (count($actions) >= 3) {
            return $actions;
        }

        // Enrich with AI reasoning only when we still need more actions.
        $ai = $this->aiSuggestions($s, $actions);
        return array_merge($actions, $ai);
    }

    private function aiSuggestions(Startup $s, array $existing): array
    {
        if (!$this->provider) return [];

        try {
            $count = 3 - count($existing);
            $raw = $this->provider->generateStructured(
                'You propose startup actions grounded ONLY in the provided context. Do not invent facts.',
                "Suggest up to {$count} more prioritized next actions." . "\n" . $this->contextForPrompt($s)
                . "\nReturn JSON: " . '{"actions":[{"title":"...","reason":"...","priority":"high|medium|low"}]}'
            );
            $out = [];
            foreach (($raw['actions'] ?? []) as $a) {
                if (!is_array($a)) continue;
                $title = $a['title'] ?? null;
                $reason = $a['reason'] ?? null;
                if (!is_string($title) || !is_string($reason) || trim($title) === '' || trim($reason) === '') continue;
                if (mb_strlen($title) > 255 || mb_strlen($reason) > 1000) continue;
                $p = in_array($a['priority'] ?? null, ['high','medium','low'], true) ? $a['priority'] : 'medium';
                $out[] = ['title' => trim($title), 'reason' => trim($reason), 'priority' => $p, 'source' => 'ai_suggestion'];
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    private function contextForPrompt(Startup $s): string
    {
        $goals = $s->goals()->where('status', 'active')->get(['title','progress','deadline']);
        $tasks = $s->tasks()->whereIn('status', ['pending','in_progress'])->limit(8)->get(['title','priority','due_date']);
        $acts = $s->activities()->latest('occurred_at')->limit(10)->get(['title','type','occurred_at']);
        return json_encode([
            'startup' => $s->only(['name','stage','problem','target_customers']),
            'goals' => $goals->toArray(),
            'tasks' => $tasks->toArray(),
            'activities' => $acts->toArray(),
        ]);
    }
}
