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
    // CLI php.exe для фона. Под Apache OpenServer PHP_BINARY=httpd.exe — без этого не стартует.
    'php_cli' => env('ASR_PHP'),
    'workdir' => env('ASR_WORKDIR', base_path('services/asr')),
    'cli' => env('ASR_CLI', 'cli.py'),
    'timeout_sec' => (int) env('ASR_TIMEOUT_SEC', 300),
];
