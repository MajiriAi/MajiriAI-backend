<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\OwnsStartupResources;
use App\Models\AiInsight;
use App\Models\Activity;
use App\Models\Conversation;
use App\Models\Goal;
use App\Models\Startup;
use App\Models\Task;
use App\Services\AI\AiService;
use App\Services\AI\MemoryService;
use App\Services\AI\NextActionService;
use App\Services\AI\StartupAnalysisService;
use Illuminate\Http\Request;

class AiController extends Controller
{
    use OwnsStartupResources;

    private function active(Request $r): Startup
    {
        return Startup::whereHas('founder', fn($q) => $q->where('user_id', $r->user()->id))->latest()->firstOrFail();
    }

    // --- Conversations -------------------------------------------------------

    public function conversations(Request $r)
    {
        return Conversation::where('user_id', $r->user()->id)->latest()->get();
    }

    public function createConversation(Request $r)
    {
        $d = $r->validate(['startup_id' => 'nullable|integer', 'title' => 'nullable|string|max:255']);
        $s = isset($d['startup_id']) ? $this->startup($r, $d['startup_id']) : $this->active($r);
        return response()->json(Conversation::create([
            'startup_id' => $s->id,
            'user_id' => $r->user()->id,
            'title' => $d['title'] ?? null,
        ]), 201);
    }

    public function showConversation(Request $r, Conversation $conversation)
    {
        abort_unless($conversation->user_id === $r->user()->id, 404);
        return $conversation;
    }

    public function renameConversation(Request $r, Conversation $conversation)
    {
        abort_unless($conversation->user_id === $r->user()->id, 404);
        $d = $r->validate(['title' => 'required|string|max:255']);
        $conversation->update(['title' => $d['title']]);
        return $conversation;
    }

    public function messages(Request $r, Conversation $conversation)
    {
        $this->showConversation($r, $conversation);
        return $conversation->messages()->oldest()->get();
    }

    public function sendMessage(Request $r, Conversation $conversation, AiService $ai, MemoryService $memory)
    {
        $this->showConversation($r, $conversation);
        $d = $r->validate(['content' => 'required|string|max:8000']);
        $conversation->messages()->create(['role' => 'user', 'content' => $d['content']]);

        try {
            $response = $ai->reply($conversation->startup, $conversation, $d['content']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        $assistant = $conversation->messages()->create(['role' => 'assistant', 'content' => $response]);
        $memory->extract($conversation->startup, $d['content'] . "\nAI RESPONSE:\n" . $response);
        return response()->json($assistant, 201);
    }

    // --- Insights & analysis ------------------------------------------------

    public function insights(Request $r)
    {
        return AiInsight::whereHas('startup.founder', fn($q) => $q->where('user_id', $r->user()->id))->latest()->get();
    }

    /** Full structured analysis (does not persist). */
    public function analysis(Request $r, StartupAnalysisService $analysis)
    {
        $s = $this->active($r);
        try {
            return $analysis->analyze($s);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
    }

    /** Persist an insight derived deterministically from stored data (no AI). */
    public function analyze(Request $r, StartupAnalysisService $analysis)
    {
        $s = $this->active($r);
        try {
            $result = $analysis->analyzeAndPersist($s);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
        return response()->json($result, 201);
    }

    /** Persistent goal-health summary (fully deterministic). */
    public function goalHealth(Request $r)
    {
        $s = $this->active($r);
        $goals = $s->goals()->where('status', 'active')->get();
        return ['goals' => app(\App\Services\AI\GoalHealthService::class)->assessAll($goals)];
    }

    /** Grounded, prioritized next actions (deterministic + AI enrichment). */
    public function nextActions(Request $r, NextActionService $service)
    {
        $s = $this->active($r);
        return ['actions' => $service->forStartup($s)];
    }

    /** Convert an AI recommendation into a real task (user-confirmed). */
    public function recommendationToTask(Request $r)
    {
        $d = $r->validate([
            'startup_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'reason' => 'nullable|string|max:1000',
            'priority' => 'sometimes|in:low,medium,high',
            'goal_id' => 'nullable|integer',
        ]);

        $s = $this->startup($r, $d['startup_id']);

        $goalId = null;
        if (!empty($d['goal_id'])) {
            $goal = Goal::where('id', $d['goal_id'])->where('startup_id', $s->id)->first();
            $goalId = $goal?->id;
        }

        $task = $s->tasks()->create([
            'title' => $d['title'],
            'description' => $d['reason'] ?? null,
            'status' => 'pending',
            'priority' => $d['priority'] ?? 'medium',
            'goal_id' => $goalId,
        ]);

        return response()->json($task, 201);
    }

    // --- Contextual AI actions ----------------------------------------------

    public function analyzeGoal(Request $r, Goal $goal, AiService $ai)
    {
        $this->owns($r, $goal);
        $tasks = $goal->tasks()->get(['title', 'status', 'priority']);
        $health = app(\App\Services\AI\GoalHealthService::class)->assess($goal);
        $ctx = ['kind' => 'goal', 'goal' => $goal->only(['title', 'description', 'progress', 'deadline', 'status']), 'health' => $health, 'tasks' => $tasks->toArray()];
        return $this->contextualReply($r, $goal->startup, $ctx, 'Analyze this goal: what is helping, what is blocking, and what should I do about it?');
    }

    public function analyzeTask(Request $r, Task $task, AiService $ai)
    {
        $this->owns($r, $task);
        $ctx = ['kind' => 'task', 'task' => $task->only(['title', 'description', 'status', 'priority', 'due_date'])];
        return $this->contextualReply($r, $task->startup, $ctx, 'Help me make progress on this task: what is the best way to complete it and why?');
    }

    public function analyzeActivity(Request $r, Activity $activity, AiService $ai)
    {
        $this->owns($r, $activity);
        $ctx = ['kind' => 'activity', 'activity' => $activity->only(['title', 'description', 'type', 'occurred_at'])];
        return $this->contextualReply($r, $activity->startup, $ctx, 'What does this activity mean for my startup and what should I do next?');
    }

    public function nextMove(Request $r, AiService $ai)
    {
        $s = $this->active($r);
        $state = app(\App\Services\AI\FounderStateAnalyzer::class)->analyze($s);
        $ctx = ['kind' => 'dashboard', 'state_analysis' => $state];
        return $this->contextualReply($r, $s, $ctx, "What's my next move? Give me one clear, grounded priority with a reason.");
    }

    private function contextualReply(Request $r, Startup $s, array $ctx, string $prompt)
    {
        try {
            $text = app(AiService::class)->contextual($s, $ctx, $prompt);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
        return ['content' => $text];
    }
}
