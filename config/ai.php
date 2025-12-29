<?php

return [

    'default_models' => [
        env('AI_MODEL_PRIMARY', 'google/gemini-2.0-flash-exp:free'),
        env('AI_MODEL_SECONDARY', 'qwen/qwen3-coder:free'),
        env('AI_MODEL_TERTIARY', 'meta-llama/llama-3.2-3b-instruct:free'),
    ],

    'auto_discovery' => [
        'enabled' => env('AI_AUTO_DISCOVERY', true),
        'cache_ttl' => 21600,
        'max_models' => 10,
        
        'filters' => [
            'free_only' => true,
            'type' => 'chat',
            'min_ranking' => 50,
        ],
    ],

    'fallback' => [
        'max_retries' => 3,
        'timeout_per_model' => 10,
        'total_timeout' => 30,
        'use_discovery_on_failure' => true,
    ],

    'cache' => [
        'key_prefix' => 'ai_models_',
        'driver' => env('AI_CACHE_DRIVER', null),
    ],

];
