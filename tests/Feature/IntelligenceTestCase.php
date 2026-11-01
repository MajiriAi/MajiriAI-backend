<?php

namespace Tests\Feature;

use App\Services\AI\AiProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeAiProvider;
use Tests\TestCase;

/**
 * Base for intelligence-layer feature tests. Swaps the real Gemini provider for
 * a deterministic fake so nothing depends on a live network call.
 */
abstract class IntelligenceTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FakeAiProvider::reset();
        $this->app->instance(AiProviderInterface::class, new FakeAiProvider());
    }

    protected function founderUser(array $userAttrs = []): \App\Models\User
    {
        $user = \App\Models\User::factory()->create($userAttrs);
        \App\Models\Founder::create(['user_id' => $user->id]);
        return $user;
    }

    protected function startupFor(\App\Models\User $user, array $attrs = []): \App\Models\Startup
    {
        return \App\Models\Startup::factory()->create(array_merge(['founder_id' => $user->founder->id], $attrs));
    }
}
