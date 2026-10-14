<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Локальный ASR (внутри messenger, не внешний HTTP как api-llm)
    |--------------------------------------------------------------------------
    |
    | Laravel Job запускает CLI: {python} services/asr/cli.py <file>
    | По умолчанию берётся venv: services/asr/.venv/...
    |
    */
    'python' => env('ASR_PYTHON'),
    'workdir' => env('ASR_WORKDIR', base_path('services/asr')),
    'cli' => env('ASR_CLI', 'cli.py'),
    'timeout_sec' => (int) env('ASR_TIMEOUT_SEC', 300),
];
