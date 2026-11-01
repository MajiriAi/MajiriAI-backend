<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Goal;
use App\Models\Task;
use App\Models\User;
use App\Services\AI\AiContextBuilder;
use App\Services\AI\GoalHealthService;
use App\Services\AI\NextActionService;
use Illuminate\Support\Carbon;
use Tests\Support\FakeAiProvider;

class FounderIntelligenceTest extends IntelligenceTestCase
{
    public function test_context_builder_returns_grounded_data_without_cross_user_leakage(): void
    {
        $a = $this->founderUser();
        $aStartup = $this->startupFor($a, ['name' => 'Alpha', 'stage' => 'validation']);
        $aStartup->goals()->create(['title' => 'Alpha goal', 'progress' => 50]);
        $aStartup->tasks()->create(['title' => 'Alpha task', 'status' => 'pending', 'priority' => 'high']);
        $aStartup->activities()->create(['title' => 'Interview', 'type' => 'customer_interview', 'occurred_at' => now()]);
        $aStartup->memories()->create(['content' => 'Alpha memory', 'category' => 'customer', 'importance' => 8, 'confidence' => 'explicit']);

        $b = $this->founderUser();
        $bStartup = $this->startupFor($b, ['name' => 'Beta', 'stage' => 'idea']);
        $b->founder->update(['skills' => 'Marketing']);

        $builder = app(AiContextBuilder::class);
        $structured = $builder->buildStructured($aStartup);

        $this->assertSame('Alpha', $structured['startup']['name']);
        $this->assertSame('Alpha goal', $structured['active_goals'][0]['title']);
        $this->assertSame('Alpha task', $structured['tasks'][0]['title']);
        $this->assertSame('Alpha memory', $structured['memories'][0]['content']);
        $this->assertSame('customer_interview', $structured['activities'][0]['type']);

        // Beta's skills must never leak into Alpha's context.
        $this->assertNotSame('Marketing', $structured['founder']['skills'] ?? null);
        $this->assertStringNotContainsString('Beta', $builder->build($aStartup));
    }

    public function test_goal_health_recognises_healthy_at_risk_stalled_and_completed(): void
    {
        $u = $this->founderUser();
        $s = $this->startupFor($u);

        $completed = $s->goals()->create(['title' => 'Done', 'progress' => 100]);
        $stalled = $s->goals()->create(['title' => 'Stalled', 'progress' => 10]);
        $stalled->timestamps = false;
        $stalled->updated_at = Carbon::now()->subDays(20);
        $stalled->save();

        $atRisk = $s->goals()->create(['title' => 'At risk', 'progress' => 30, 'deadline' => Carbon::now()->addDay()]);
        $healthy = $s->goals()->create(['title' => 'Healthy']);
        $s->activities()->create(['title' => 'Progress', 'type' => 'product_development', 'occurred_at' => now()]);

        $service = app(GoalHealthService::class);

        $this->assertSame('completed', $service->assess($completed)['status']);
        $this->assertSame('stalled', $service->assess($stalled)['status']);
        $this->assertSame('at_risk', $service->assess($atRisk)['status']);
        $this->assertSame('healthy', $service->assess($healthy)['status']);
    }

    public function test_next_actions_are_grounded_in_overdue_tasks_and_stage_gaps(): void
    {
        $u = $this->founderUser();
        $s = $this->startupFor($u, ['stage' => 'validation']);

        // Overdue task -> high priority action.
        $s->tasks()->create(['title' => 'Pay invoice', 'status' => 'pending', 'priority' => 'high', 'due_date' => Carbon::now()->subDay()]);

        $actions = app(NextActionService::class)->forStartup($s);

        $this->assertNotEmpty($actions);
        $this->assertStringContainsString('Pay invoice', $actions[0]['title']);
        $this->assertSame('overdue_task', $actions[0]['source']);
        $this->assertSame('high', $actions[0]['priority']);

        // No customer interviews in validation -> stage gap action.
        $titles = implode(' ', array_column($actions, 'title'));
        $this->assertStringContainsString('customer interviews', strtolower($titles));
    }

    public function test_recommendation_can_be_converted_to_a_task_linked_to_a_goal(): void
    {
        $u = $this->founderUser();
        $s = $this->startupFor($u);
        $goal = $s->goals()->create(['title' => 'Validation', 'progress' => 20]);

        $this->actingAs($u, 'sanctum')
            ->postJson('/api/ai/recommendation/to-task', [
                'startup_id' => $s->id,
                'title' => 'Interview 5 customers',
                'reason' => 'Grounded in stage gap',
                'priority' => 'high',
                'goal_id' => $goal->id,
            ])
            ->assertCreated()
            ->assertJson(['title' => 'Interview 5 customers', 'goal_id' => $goal->id, 'status' => 'pending']);
    }

    public function test_recommendation_does_not_allow_creating_tasks_for_another_founders_goal(): void
    {
        $u = $this->founderUser();
        $s = $this->startupFor($u);
        $other = $this->founderUser();
        $foreignStartup = $this->startupFor($other);
        $foreignGoal = $foreignStartup->goals()->create(['title' => 'Theirs', 'progress' => 0]);

        $this->actingAs($u, 'sanctum')
            ->postJson('/api/ai/recommendation/to-task', [
                'startup_id' => $s->id,
                'title' => 'My task',
                'goal_id' => $foreignGoal->id,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('tasks', ['title' => 'My task', 'goal_id' => null]);
    }

    public function test_analysis_validates_ai_output_and_persists_insights(): void
    {
        FakeAiProvider::respondStructured([
            'overall_summary' => 'Progressing.',
            'strengths' => ['Strong validation'],
            'concerns' => ['Thin customer base'],
            'opportunities' => ['Pricing lever'],
            'recommended_focus' => 'Talk to customers',
            'next_actions' => [
                ['title' => 'Run interviews', 'reason' => 'Needed', 'priority' => 'high'],
                ['title' => 'Bad action missing fields', 'priority' => 'high'],
                ['title' => 'Okay action', 'reason' => 'Valid', 'priority' => 'invalid-priority'],
            ],
        ]);

        $u = $this->founderUser();
        $s = $this->startupFor($u);

        $response = $this->actingAs($u, 'sanctum')->postJson('/api/ai/analyze');

        $response->assertStatus(201)
            ->assertJson(['overall_summary' => 'Progressing.', 'recommended_focus' => 'Talk to customers']);

        $data = $response->json();
        $this->assertArrayHasKey('strengths', $data);
        $this->assertSame('Run interviews', $data['next_actions'][0]['title']);
        // Invalid actions (missing reason / bad priority) are rejected or normalized.
        foreach ($data['next_actions'] as $a) {
            $this->assertNotEmpty($a['title']);
            $this->assertNotEmpty($a['reason']);
            $this->assertContains($a['priority'], ['high', 'medium', 'low']);
        }

        // Insights were persisted from the validated analysis.
        $this->assertGreaterThanOrEqual(1, $s->aiInsights()->count());
    }

    public function test_analysis_returns_503_when_provider_is_unavailable(): void
    {
        FakeAiProvider::failNext();

        $u = $this->founderUser();
        $s = $this->startupFor($u);

        $this->actingAs($u, 'sanctum')->postJson('/api/ai/analyze')->assertStatus(503);
    }

    public function test_analysis_rejects_malformed_structured_output(): void
    {
        FakeAiProvider::respondStructured('not-an-array');

        $u = $this->founderUser();
        $this->startupFor($u);

        // FakeAiProvider treats non-array as failure -> 503.
        $this->actingAs($u, 'sanctum')->postJson('/api/ai/analyze')->assertStatus(503);
    }

    public function test_analysis_is_scoped_to_the_current_founders_startup(): void
    {
        $u = $this->founderUser();
        $this->startupFor($u, ['name' => 'Mine']);

        $other = $this->founderUser();
        $this->startupFor($other, ['name' => 'Not yours']);

        FakeAiProvider::respondStructured([
            'overall_summary' => 'summary',
            'strengths' => [],
            'concerns' => [],
            'opportunities' => [],
            'recommended_focus' => 'focus',
            'next_actions' => [],
        ]);

        $this->actingAs($u, 'sanctum')->getJson('/api/ai/analysis')->assertOk();
    }

    public function test_dashboard_is_deterministic_and_contains_commander_data(): void
    {
        $u = $this->founderUser();
        $s = $this->startupFor($u, ['stage' => 'validation']);
        $s->goals()->create(['title' => 'Validate', 'progress' => 10]);

        $res = $this->actingAs($u, 'sanctum')->getJson('/api/dashboard')->assertOk()->json();

        $this->assertArrayHasKey('founder_status', $res);
        $this->assertArrayHasKey('current_focus', $res);
        $this->assertArrayHasKey('goal_health', $res);
        $this->assertArrayHasKey('recommended_actions', $res);
        $this->assertSame('validation', $res['founder_status']['stage']);
    }

    public function test_founders_cannot_see_each_others_context_via_analysis(): void
    {
        $u = $this->founderUser();
        $mine = $this->startupFor($u, ['name' => 'MyCo']);
        $mine->memories()->create(['content' => 'Secret memory', 'category' => 'strategy', 'importance' => 9, 'confidence' => 'explicit']);

        $other = $this->founderUser();
        $theirs = $this->startupFor($other, ['name' => 'TheirCo']);

        $builder = app(AiContextBuilder::class);
        $theirsContext = $builder->build($theirs);

        $this->assertStringNotContainsString('Secret memory', $theirsContext);
        $this->assertStringNotContainsString('MyCo', $theirsContext);
    }
}