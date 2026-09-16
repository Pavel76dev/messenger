<?php

$path = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';

if (! is_file($path)) {
    fwrite(STDERR, "NO_ENV\n");
    exit(1);
}

$content = file_get_contents($path);

$pairs = [
    'BROADCAST_DRIVER' => 'pusher',
    'PUSHER_APP_ID' => '1',
    'PUSHER_APP_KEY' => 'messenger-key',
    'PUSHER_APP_SECRET' => 'messenger-secret',
    'PUSHER_APP_CLUSTER' => 'mt1',
    'PUSHER_HOST' => '127.0.0.1',
    'PUSHER_PORT' => '6001',
    'PUSHER_SCHEME' => 'http',
    'VITE_PUSHER_APP_KEY' => '"${PUSHER_APP_KEY}"',
    'VITE_PUSHER_APP_CLUSTER' => '"${PUSHER_APP_CLUSTER}"',
    'VITE_PUSHER_HOST' => '127.0.0.1',
    'VITE_PUSHER_PORT' => '6001',
    'VITE_PUSHER_SCHEME' => 'http',
    'VITE_PUSHER_FORCE_TLS' => 'false',
];

foreach ($pairs as $key => $value) {
    $line = $key.'='.$value;
    $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

    if (preg_match($pattern, $content)) {
        $content = preg_replace($pattern, $line, $content, 1);
    } else {
        $content = rtrim($content).PHP_EOL.$line.PHP_EOL;
    }
}

file_put_contents($path, $content);
echo "UPDATED\n";
