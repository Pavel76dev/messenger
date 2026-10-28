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
        'Ты ассистент учебного мессенджера. Отвечай кратко и по делу на языке пользователя. '
        .'Если ниже есть блок «Контекст данных пользователя» — у тебя ЕСТЬ доступ к этим данным: '
        .'чатам, документам, транскриптам и спискам задач. Опирайся на них и не утверждай, '
        .'что не видишь файлы/чат/задачи. Не выдумывай то, чего нет в контексте; '
        .'если нужных данных в блоке нет — скажи об этом прямо.'
    ),

    /*
    |--------------------------------------------------------------------------
    | Workspace context (feature 017)
    |--------------------------------------------------------------------------
    */

    // Локальные модели плохо переваривают огромный дамп — держим компактно.
    'context_max_chars' => (int) env('LLM_CONTEXT_MAX_CHARS', 8000),

    'context_max_messages_total' => (int) env('LLM_CONTEXT_MAX_MESSAGES', 60),

    'context_max_messages_per_conversation' => (int) env('LLM_CONTEXT_MAX_MESSAGES_PER_CHAT', 25),

    'context_max_attachments' => (int) env('LLM_CONTEXT_MAX_ATTACHMENTS', 10),

    'context_max_clips' => (int) env('LLM_CONTEXT_MAX_CLIPS', 8),

    'router_timeout_sec' => (int) env('LLM_ROUTER_TIMEOUT_SEC', 45),

    'router_prompt' => env(
        'LLM_ROUTER_PROMPT',
        'Ты классификатор запросов к ассистенту мессенджера. '
        .'Нужен ли доступ к данным пользователя: чатам, вложениям, транскриптам аудио, спискам задач? '
        .'Бытовой разговор без личных данных — need_workspace=false. '
        .'Любые «мои задачи», to-do, дела, переписка, файлы, записи, «что я говорил», '
        .'суммировать/описать по моим данным — need_workspace=true. '
        .'Ответь ТОЛЬКО JSON: {"need_workspace":true} или {"need_workspace":false}.'
    ),

    'router_max_tokens' => (int) env('LLM_ROUTER_MAX_TOKENS', 64),

];
