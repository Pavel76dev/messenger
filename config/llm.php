<?php

return [

    /*
    |--------------------------------------------------------------------------
    | api-llm (local FastAPI proxy to LM Studio / Ollama)
    |--------------------------------------------------------------------------
    */

    'api_url' => rtrim(env('LLM_API_URL', 'http://127.0.0.1:8050'), '/'),

    'api_token' => env('LLM_API_TOKEN'),

    'target' => env('LLM_TARGET', 'auto'),

    'model' => env('LLM_DEFAULT_MODEL'),

    'timeout_sec' => (int) env('LLM_TIMEOUT_SEC', 120),

    'history_limit' => (int) env('LLM_HISTORY_LIMIT', 20),

    'bot_email' => env('LLM_BOT_EMAIL', 'ai@messenger.local'),

    'bot_name' => env('LLM_BOT_NAME', 'Ассистент'),

    'system_prompt' => env(
        'LLM_SYSTEM_PROMPT',
        'Ты ассистент учебного мессенджера. Отвечай кратко и по делу на языке пользователя.'
    ),

];
