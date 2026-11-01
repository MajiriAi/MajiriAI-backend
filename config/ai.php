<?php

// Custom application config for the AI copilot. Kept separate from Laravel's
// built-in config files so provider swaps stay a one-file change.
return [
    'provider' => env('AI_PROVIDER', 'gemini'),
    'api_key' => env('GEMINI_API_KEY'),
    'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),

    // Caps on how much context AiContextBuilder pulls per request, so prompts
    // stay bounded even for startups with a long history.
    'max_context_activities' => env('AI_MAX_CONTEXT_ACTIVITIES', 15),
    'max_context_memories' => env('AI_MAX_CONTEXT_MEMORIES', 25),
    'max_context_messages' => env('AI_MAX_CONTEXT_MESSAGES', 10),
    'max_context_tasks' => env('AI_MAX_CONTEXT_TASKS', 12),
    'max_context_insights' => env('AI_MAX_CONTEXT_INSIGHTS', 5),
    'max_context_goals' => env('AI_MAX_CONTEXT_GOALS', 8),
];
