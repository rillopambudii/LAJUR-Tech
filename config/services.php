<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // AI business assistant provider: 'anthropic' or 'openai' (OpenAI-compatible:
    // Groq, Ollama, OpenRouter, LM Studio, OpenAI, …).
    'ai' => [
        'provider' => env('AI_PROVIDER', 'anthropic'),
    ],

    // Claude (Anthropic) driver.
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-4-8'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
    ],

    // OpenAI-compatible driver (default values target Groq's free API).
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.groq.com/openai/v1'),
        'model' => env('OPENAI_MODEL', 'llama-3.3-70b-versatile'),
    ],

    // Google Maps (unit tracking page).
    'google' => [
        'maps_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    // Unit tracking. Demo mode fabricates positions (near Samarinda) so the map is
    // usable before the Traccar integration feeds real data into vehicle_positions.
    'tracking' => [
        'demo' => env('TRACKING_DEMO', false),
    ],

    // Optional CA bundle path for outbound HTTPS (Guzzle "verify"). Null = use the
    // system/PHP default. Set on dev machines where PHP has no CA bundle configured.
    'ca_bundle' => env('CURL_CA_BUNDLE') ?: null,

    // Which PaymentGateway driver to use: 'midtrans' or 'manual' (offline).
    'payment' => [
        'gateway' => env('PAYMENT_GATEWAY', 'manual'),
    ],

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
    ],

];
