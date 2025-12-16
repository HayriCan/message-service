<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Message Character Limit
    |--------------------------------------------------------------------------
    |
    | Maximum allowed character length for message content.
    | Standard SMS is 160 characters, but can be adjusted as needed.
    |
    */

    'char_limit' => env('MESSAGE_CHAR_LIMIT', 160),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configuration for message sending rate limits.
    | Default: 2 messages per 5 seconds
    |
    */

    'rate_limit' => [
        'messages_per_batch' => 2,
        'batch_interval_seconds' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Cache
    |--------------------------------------------------------------------------
    |
    | Configuration for caching sent message info in Redis.
    |
    */

    'cache' => [
        'prefix' => 'message',
        'ttl_hours' => 24,
    ],

];
