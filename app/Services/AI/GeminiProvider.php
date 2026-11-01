<?php
namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiProvider implements AiProviderInterface
{
    private function request(string $system, string $prompt, bool $json = false): array
    {
        $key = config('ai.api_key');

        if (!$key) {
            throw new RuntimeException('AI is not configured. Set GEMINI_API_KEY on the backend.');
        }

        $model = config('ai.model');
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $key;

        $body = [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
        ];

        if ($json) {
            $body['generationConfig'] = ['responseMimeType' => 'application/json'];
        }

        $response = Http::timeout(25)->acceptJson()->post($url, $body);

        if (!$response->successful()) {
            Log::error('Gemini API error', [
                'status' => $response->status(),
                'model'  => $model,
                'body'   => $response->body(),
            ]);

            throw new RuntimeException(
                'The AI provider is temporarily unavailable. '
                . '(HTTP ' . $response->status() . ') '
                . $response->body()
            );
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

        if (!is_string($text) || $text === '') {
            Log::error('Gemini API unexpected response shape', ['body' => $response->json()]);
            throw new RuntimeException('The AI provider returned an invalid response.');
        }

        return ['text' => $text];
    }

    public function generate(string $system, string $prompt): string
    {
        return $this->request($system, $prompt)['text'];
    }

    public function generateStructured(string $system, string $prompt): array
    {
        $d = json_decode($this->request($system, $prompt, true)['text'], true);

        if (!is_array($d)) {
            throw new RuntimeException('The AI provider returned malformed structured data.');
        }

        return $d;
    }
}
