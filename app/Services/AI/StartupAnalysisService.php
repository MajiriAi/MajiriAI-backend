<?php
namespace App\Services\AI;

use App\Models\AiInsight;
use App\Models\Startup;
use RuntimeException;

/**
 * Structured "analyze my startup" capability. Uses Gemini for reasoning over a
 * grounded context, but validates every returned field server-side and never
 * lets the AI invent facts (enforced by prompt + validation below).
 */
class StartupAnalysisService
{
    public const PRIORITIES = ['high', 'medium', 'low'];
    public const INSIGHT_TYPES = ['warning', 'recommendation', 'opportunity', 'observation'];

    public function __construct(
        private AiProviderInterface $provider,
        private AiContextBuilder $context,
    ) {}

    /**
     * @return array{overall_summary:string, strengths:string[], concerns:string[], opportunities:string[], recommended_focus:string, next_actions:array<int,array{title:string,reason:string,priority:string}>}
     */
    public function analyze(Startup $s): array
    {
        $prompt = "Analyze this startup's current state using ONLY the provided context. "
            . "Do not invent facts. Missing information should be reflected as uncertainty, not fabricated. "
            . "Return strict JSON matching this shape:\n"
            . '{"overall_summary":"...","strengths":["..."],"concerns":["..."],"opportunities":["..."],"recommended_focus":"...","next_actions":[{"title":"...","reason":"...","priority":"high|medium|low"}]}';

        $raw = $this->provider->generateStructured(
            $this->reasonSystemPrompt(),
            $this->context->build($s) . "\n\n" . $prompt
        );

        return $this->validate($raw);
    }

    public function analyzeAndPersist(Startup $s): array
    {
        $result = $this->analyze($s);

        $mapping = [
            'strengths' => 'strength',
            'concerns' => 'warning',
            'opportunities' => 'opportunity',
        ];
        foreach ($mapping as $listKey => $type) {
            foreach (array_slice($result[$listKey], 0, 3) as $item) {
                $s->aiInsights()->create([
                    'title' => ucfirst($listKey) . ' from analysis',
                    'content' => $item,
                    'type' => $this->mapType($type),
                    'priority' => $this->mapPriority($listKey, $result),
                ]);
            }
        }

        return $result;
    }

    private function reasonSystemPrompt(): string
    {
        return implode("\n", [
            'You analyze startup progress strictly from the provided context.',
            'Do not invent facts, numbers, customers, or outcomes.',
            'Every strength, concern, opportunity and recommendation must trace to something in the context.',
            'If the context is too thin to be sure about something, say so rather than guessing.',
            'Respond with valid JSON only.',
        ]);
    }

    private function mapType(string $type): string
    {
        return in_array($type, self::INSIGHT_TYPES, true) ? $type : 'observation';
    }

    private function mapPriority(string $listKey, array $result): string
    {
        $p = $result['next_actions'][0]['priority'] ?? 'medium';
        return in_array($p, self::PRIORITIES, true) ? $p : 'medium';
    }

    private function validate(array $raw): array
    {
        $summary = $this->stringField($raw, 'overall_summary', 2000, 'Analysis did not produce a summary.');
        $strengths = $this->stringList($raw, 'strengths');
        $concerns = $this->stringList($raw, 'concerns');
        $opportunities = $this->stringList($raw, 'opportunities');
        $focus = $this->stringField($raw, 'recommended_focus', 500, 'Analysis did not produce a focus.');

        $actions = [];
        foreach (($raw['next_actions'] ?? []) as $a) {
            if (!is_array($a)) continue;
            $title = $this->optionalString($a, 'title', 255);
            $reason = $this->optionalString($a, 'reason', 1000);
            if ($title === null || $reason === null) continue;
            $priority = in_array($a['priority'] ?? null, self::PRIORITIES, true) ? $a['priority'] : 'medium';
            $actions[] = ['title' => $title, 'reason' => $reason, 'priority' => $priority];
        }

        return [
            'overall_summary' => $summary,
            'strengths' => $strengths,
            'concerns' => $concerns,
            'opportunities' => $opportunities,
            'recommended_focus' => $focus,
            'next_actions' => array_slice($actions, 0, 5),
        ];
    }

    private function stringField(array $raw, string $key, int $max, string $error): string
    {
        $v = $raw[$key] ?? null;
        if (!is_string($v) || trim($v) === '' || mb_strlen($v) > $max) {
            throw new RuntimeException($error);
        }
        return trim($v);
    }

    private function stringList(array $raw, string $key): array
    {
        $list = $raw[$key] ?? [];
        if (!is_array($list)) return [];
        $out = [];
        foreach ($list as $item) {
            if (is_string($item) && trim($item) !== '' && mb_strlen($item) <= 1000) {
                $out[] = trim($item);
            }
        }
        return array_slice($out, 0, 5);
    }

    private function optionalString(array $a, string $key, int $max): ?string
    {
        $v = $a[$key] ?? null;
        if (!is_string($v) || trim($v) === '' || mb_strlen($v) > $max) return null;
        return trim($v);
    }
}
