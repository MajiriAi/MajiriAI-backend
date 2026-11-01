<?php
namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\Startup;

class AiService
{
    public function __construct(private AiProviderInterface $provider, private AiContextBuilder $context) {}

    /**
     * Chat reply grounded in the founder's stored context.
     */
    public function reply(Startup $s, Conversation $c, string $message): string
    {
        return $this->provider->generate(
            $this->systemPrompt(),
            $this->context->build($s, $c) . "\n\nCURRENT USER REQUEST\n" . $message
        );
    }

    /**
     * Free-form reasoning against a granted set of entities (goal/task/activity).
     * @param array<string,mixed> $entityContext
     */
    public function contextual(Startup $s, array $entityContext, string $prompt): string
    {
        $base = $this->context->buildStructured($s);
        return $this->provider->generate(
            $this->systemPrompt(),
            "STARTUP/FOUNDER CONTEXT\n" . json_encode([
                'startup' => $base['startup'],
                'stage' => $base['stage'],
                'active_goals' => $base['active_goals'],
                'state_analysis' => $base['state_analysis'],
            ]) . "\n\nENTITY CONTEXT\n" . json_encode($entityContext) . "\n\nREQUEST\n" . $prompt
        );
    }

    public function systemPrompt(): string
    {
        return implode("\n", [
            'You are a practical AI startup copilot grounded in the founder\'s actual stored data.',
            'Use ONLY the supplied context. Clearly distinguish:',
            '- KNOWN: facts directly stored in the context.',
            '- INFERRED: reasonable conclusions you draw FROM the stored context.',
            '- UNKNOWN: anything not present in the context.',
            'If the data to answer is missing, say "I don\'t have enough information to determine that yet."',
            'Never invent facts, numbers, customers, or decisions that are not in the context.',
            'Challenge weak assumptions and give concrete next actions grounded in stored goals/tasks/memories.',
            'You are advisory only; never claim to perform actions you did not take.',
        ]);
    }
}
