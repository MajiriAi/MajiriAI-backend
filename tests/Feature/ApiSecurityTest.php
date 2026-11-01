<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Founder;
use App\Models\Goal;
use App\Models\Startup;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_and_protected_endpoints(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'A',
            'email' => 'a@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated()->assertJsonStructure(['token']);

        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_user_cannot_read_another_startup(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $s = Startup::create([
            'founder_id' => Founder::create(['user_id' => $b->id])->id,
            'name' => 'Hidden',
            'stage' => 'idea',
        ]);

        $this->actingAs($a, 'sanctum')
            ->getJson('/api/startups/' . $s->id)
            ->assertNotFound();
    }

    // --- Cross-tenant update protection (C1 regression) ----------------------
    // The resource controllers previously reused one rules() set for store and
    // update, so startup_id / goal_id were mass-assignable on update and a user
    // could reassign their record into another founder's startup. These tests
    // lock that down.

    /** @return array{0:User,1:Startup,2:User,3:Startup} */
    private function twoFoundersWithStartups(): array
    {
        $a = User::factory()->create();
        $startupA = Startup::factory()->create(['founder_id' => Founder::create(['user_id' => $a->id])->id]);

        $b = User::factory()->create();
        $startupB = Startup::factory()->create(['founder_id' => Founder::create(['user_id' => $b->id])->id]);

        return [$a, $startupA, $b, $startupB];
    }

    public function test_goal_cannot_be_moved_to_another_startup_via_update(): void
    {
        [$a, $startupA, , $startupB] = $this->twoFoundersWithStartups();
        $goal = $startupA->goals()->create(['title' => 'My goal']);

        $this->actingAs($a, 'sanctum')
            ->putJson("/api/goals/{$goal->id}", [
                'title' => 'Renamed',
                'startup_id' => $startupB->id, // attempt cross-tenant move
            ])
            ->assertSuccessful();

        $this->assertDatabaseHas('goals', [
            'id' => $goal->id,
            'startup_id' => $startupA->id, // unchanged
        ]);
    }

    public function test_task_cannot_be_moved_to_another_startup_via_update(): void
    {
        [$a, $startupA, , $startupB] = $this->twoFoundersWithStartups();
        $task = $startupA->tasks()->create(['title' => 'My task']);

        $this->actingAs($a, 'sanctum')
            ->putJson("/api/tasks/{$task->id}", [
                'title' => 'Renamed',
                'startup_id' => $startupB->id,
            ])
            ->assertSuccessful();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'startup_id' => $startupA->id,
        ]);
    }

    public function test_task_cannot_be_linked_to_another_founders_goal_via_update(): void
    {
        [$a, $startupA, , $startupB] = $this->twoFoundersWithStartups();
        $task = $startupA->tasks()->create(['title' => 'My task']);
        $foreignGoal = $startupB->goals()->create(['title' => 'Their goal']);

        $this->actingAs($a, 'sanctum')
            ->putJson("/api/tasks/{$task->id}", [
                'title' => 'Renamed',
                'goal_id' => $foreignGoal->id,
            ])
            ->assertSuccessful();

        // The foreign goal_id must never be stored against this task.
        $this->assertDatabaseMissing('tasks', [
            'id' => $task->id,
            'goal_id' => $foreignGoal->id,
        ]);
    }

    public function test_task_cannot_be_linked_to_another_founders_goal_on_store(): void
    {
        [$a, $startupA, , $startupB] = $this->twoFoundersWithStartups();
        $foreignGoal = $startupB->goals()->create(['title' => 'Their goal']);

        $this->actingAs($a, 'sanctum')
            ->postJson('/api/tasks', [
                'startup_id' => $startupA->id,
                'title' => 'New task',
                'goal_id' => $foreignGoal->id,
            ])
            ->assertCreated();

        $this->assertDatabaseMissing('tasks', [
            'title' => 'New task',
            'goal_id' => $foreignGoal->id,
        ]);
    }

    public function test_activity_cannot_be_moved_to_another_startup_via_update(): void
    {
        [$a, $startupA, , $startupB] = $this->twoFoundersWithStartups();
        $activity = $startupA->activities()->create([
            'title' => 'My activity',
            'type' => 'note',
            'occurred_at' => now(),
        ]);

        $this->actingAs($a, 'sanctum')
            ->putJson("/api/activities/{$activity->id}", [
                'title' => 'Renamed',
                'startup_id' => $startupB->id,
            ])
            ->assertSuccessful();

        $this->assertDatabaseHas('activities', [
            'id' => $activity->id,
            'startup_id' => $startupA->id,
        ]);
    }
}
