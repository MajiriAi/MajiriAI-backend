<?php

namespace Tests\Feature;

use App\Models\Startup;
use App\Services\AI\MemoryService;
use Tests\Support\FakeAiProvider;

class MemoryServiceTest extends IntelligenceTestCase
{
    public function test_extract_persists_valid_memories_with_confidence(): void
    {
        FakeAiProvider::respondStructured([
            'memories' => [
                ['content' => 'Founder uses Laravel', 'category' => 'founder', 'importance' => 8, 'confidence' => 'explicit', 'key' => 'founder_skill'],
                ['content' => 'Customers are students', 'category' => 'customer', 'importance' => 6, 'confidence' => 'inferred', 'key' => 'target_customers'],
            ],
        ]);

        $s = $this->makeStartup();
        app(MemoryService::class)->extract($s, 'Some conversation text');

        $this->assertDatabaseHas('memories', ['content' => 'Founder uses Laravel', 'confidence' => 'explicit']);
        $this->assertDatabaseHas('memories', ['content' => 'Customers are students', 'confidence' => 'inferred']);
    }

    public function test_extract_rejects_malformed_memories_silently(): void
    {
        FakeAiProvider::respondStructured([
            'memories' => [
                ['content' => '', 'category' => 'founder', 'importance' => 8, 'confidence' => 'explicit'],
                ['content' => 'Bad category', 'category' => 'nonsense', 'importance' => 8, 'confidence' => 'explicit'],
                ['content' => 'Bad importance', 'category' => 'founder', 'importance' => 99, 'confidence' => 'explicit'],
                ['content' => 'Bad confidence', 'category' => 'founder', 'importance' => 5, 'confidence' => 'maybe'],
                ['content' => 'Valid one', 'category' => 'founder', 'importance' => 5, 'confidence' => 'explicit'],
            ],
        ]);

        $s = $this->makeStartup();
        app(MemoryService::class)->extract($s, 'text');

        $this->assertSame(1, $s->memories()->count());
        $this->assertDatabaseHas('memories', ['content' => 'Valid one']);
    }

    public function test_extract_never_throws_when_provider_fails(): void
    {
        FakeAiProvider::failNext();
        $s = $this->makeStartup();
        app(MemoryService::class)->extract($s, 'text'); // must not throw
        $this->assertSame(0, $s->memories()->count());
    }

    public function test_duplicate_memory_is_deduped_and_importance_boosted(): void
    {
        $s = $this->makeStartup();
        $svc = app(MemoryService::class);

        $svc->record($s, 'Pricing is a pain point', 'customer', 4, 'explicit', 'pricing_feedback');
        $this->assertSame(1, $s->memories()->count());

        $svc->record($s, 'Pricing is a pain point', 'customer', 8, 'explicit', 'pricing_feedback');
        $this->assertSame(1, $s->memories()->count());
        $this->assertSame(8, $s->memories()->first()->importance);
    }

    public function test_newer_memory_supersedes_older_conflicting_one_by_key(): void
    {
        $s = $this->makeStartup();
        $svc = app(MemoryService::class);

        $svc->record($s, 'Target customers are university students', 'customer', 7, 'explicit', 'target_customers');
        $svc->record($s, 'We now target small businesses', 'customer', 8, 'explicit', 'target_customers');

        $this->assertDatabaseHas('memories', ['content' => 'We now target small businesses', 'superseded' => false]);
        $this->assertDatabaseHas('memories', ['content' => 'Target customers are university students', 'superseded' => true]);

        // Only the active one surfaces.
        $this->assertSame('We now target small businesses', $s->memories()->where('superseded', false)->firstOrFail()->content);
    }

    public function test_context_builder_only_includes_active_memories(): void
    {
        $s = $this->makeStartup();
        $svc = app(MemoryService::class);
        $svc->record($s, 'Old target: students', 'customer', 7, 'explicit', 'target_customers');
        $svc->record($s, 'New target: small businesses', 'customer', 8, 'explicit', 'target_customers');

        $text = app(\App\Services\AI\AiContextBuilder::class)->build($s);
        $this->assertStringContainsString('New target: small businesses', $text);
        $this->assertStringNotContainsString('Old target: students', $text);
    }

    protected function makeStartup(): Startup
    {
        $u = $this->founderUser();
        return $this->startupFor($u);
    }
}