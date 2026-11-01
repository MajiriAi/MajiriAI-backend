<?php
namespace App\Services\AI;

use App\Models\Startup;

class MemoryService
{
    public const CATEGORIES = ['founder','startup','customer','market','product','strategy','decision','constraint','insight'];
    public const CONFIDENCES = ['explicit','inferred'];

    public function __construct(private AiProviderInterface $provider) {}

    /**
     * Extract durable memories from a user/AI exchange, then persist them
     * using dedup + conflict resolution. Never throws: memory must not block
     * a useful chat response.
     */
    public function extract(Startup $s, string $text): void
    {
        try {
            $d = $this->provider->generateStructured(
                'Extract only durable facts genuinely useful for future startup advice. '
                . 'A "key" is the stable subject of the fact (e.g. "target_customers", "founder_skill", "pricing_feedback"), '
                . 'used to supersede older contradicting facts about the same subject. '
                . '"confidence" is "explicit" when the founder stated the fact directly, "inferred" when you deduced it. '
                . 'Return {"memories":[{"content":"...","category":"founder|startup|customer|market|product|strategy|decision|constraint|insight","importance":1-10,"confidence":"explicit|inferred","key":"..."}]}.',
                'TEXT: ' . $text
            );

            foreach (($d['memories'] ?? []) as $m) {
                if (!$this->valid($m)) continue;
                $this->save($s, $m);
            }
        } catch (\Throwable) {
            // Memory must never prevent a useful chat response.
        }
    }

    /**
     * Explicitly persist a memory (e.g. from an activity) with a known confidence.
     */
    public function record(Startup $s, string $content, string $category, int $importance, string $confidence = 'explicit', ?string $key = null, string $source = 'manual'): void
    {
        $this->save($s, compact('content','category','importance','confidence','key','source'));
    }

    private function valid(array $m): bool
    {
        $okCategory = is_string($m['category'] ?? null) && in_array($m['category'], self::CATEGORIES, true);
        $okContent = is_string($m['content'] ?? null) && trim($m['content']) !== '' && strlen($m['content']) <= 1000;
        $okImportance = is_int($m['importance'] ?? null) ? ($m['importance'] >= 1 && $m['importance'] <= 10) : is_int((int)($m['importance'] ?? 0));
        $okConfidence = in_array($m['confidence'] ?? null, self::CONFIDENCES, true);
        return $okCategory && $okContent && $okImportance && $okConfidence;
    }

    private function save(Startup $s, array $m): void
    {
        $content = trim((string)$m['content']);
        $confidence = $m['confidence'] ?? 'explicit';
        $key = isset($m['key']) && is_string($m['key']) && trim($m['key']) !== '' ? trim($m['key']) : null;
        $importance = min(10, max(1, (int)$m['importance']));
        $source = $m['source'] ?? 'conversation';

        // Dedup: exact match on key+content, or content match when no key.
        $existing = $key
            ? $s->memories()->where('key', $key)->where('content', $content)->where('superseded', false)->first()
            : $s->memories()->where('content', $content)->where('superseded', false)->first();

        if ($existing) {
            $existing->update(['importance' => max($existing->importance, $importance)]);
            return;
        }

        // Conflict resolution: a newer, different fact about the same subject
        // supersedes the older active fact.
        if ($key) {
            $s->memories()
                ->where('key', $key)
                ->where('superseded', false)
                ->update(['superseded' => true]);
        }

        $s->memories()->create([
            'content' => $content,
            'category' => $m['category'],
            'importance' => $importance,
            'source' => $source,
            'confidence' => $confidence,
            'key' => $key,
            'superseded' => false,
        ]);
    }
}
