<?php
namespace App\Services\AI; interface AiProviderInterface { public function generate(string $system, string $prompt): string; public function generateStructured(string $system, string $prompt): array; }
