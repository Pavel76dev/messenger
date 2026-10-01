<?php

return [
    'url' => env('LIVEKIT_URL', 'ws://127.0.0.1:7880'),
    'api_key' => env('LIVEKIT_API_KEY', 'APImessengerdevkey'),
    'api_secret' => env('LIVEKIT_API_SECRET', 'messenger_livekit_secret_change_me_32b'),
    'token_ttl_seconds' => (int) env('LIVEKIT_TOKEN_TTL', 3600),
];
