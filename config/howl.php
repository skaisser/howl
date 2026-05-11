<?php

return [

    /*
    |-------------------------------------------------------------------------
    | Default driver
    |-------------------------------------------------------------------------
    | Which driver receives Howl::onDiscord()->error(...) by default.
    | In v1 only `discord` is implemented. `telegram` / `slack` are
    | reserved for future versions.
    */
    'driver' => env('HOWL_DRIVER', 'discord'),

    /*
    |-------------------------------------------------------------------------
    | Fallback driver
    |-------------------------------------------------------------------------
    | If the primary driver fails (non-2xx HTTP, timeout, network error),
    | retry with this driver. Set to null to disable fallback.
    */
    'fallback' => env('HOWL_FALLBACK', null),

    /*
    |-------------------------------------------------------------------------
    | Queue mode
    |-------------------------------------------------------------------------
    | When true, sends are dispatched as ShouldQueue jobs with 3 retries
    | and exponential backoff. When false (default), sends are sync.
    | Queue-failure events always force sync to avoid recursive loops.
    */
    'queue' => env('HOWL_QUEUE', false),
    'queue_connection' => env('HOWL_QUEUE_CONNECTION', null),
    'queue_name' => env('HOWL_QUEUE_NAME', 'default'),

    /*
    |-------------------------------------------------------------------------
    | App branding (used in webhook username and footer)
    |-------------------------------------------------------------------------
    */
    'app_name' => env('HOWL_APP_NAME', env('APP_NAME', 'App')),
    'app_env' => env('HOWL_APP_ENV', env('APP_ENV', 'local')),
    'username_format' => env('HOWL_USERNAME_FORMAT', '{severity_emoji} {app} · {env} · {channel}'),

    /*
    |-------------------------------------------------------------------------
    | Drivers configuration
    |-------------------------------------------------------------------------
    */
    'drivers' => [

        'discord' => [
            // Single default webhook URL. Per-category overrides below.
            'webhook_url' => env('HOWL_DISCORD_DEFAULT'),

            // Per-category thread IDs (v1 default routing).
            // Each category's message posts to the matching thread via `?thread_id=N`.
            // If not set, the message posts to the channel root (no thread).
            // NOTE: Driver treats HTTP 204 as success (Discord returns 204 No Content
            // on successful webhook POSTs, NOT 200).
            'threads' => [
                'errors' => env('HOWL_DISCORD_THREAD_ERRORS'),
                'warnings' => env('HOWL_DISCORD_THREAD_WARNINGS'),
                'info' => env('HOWL_DISCORD_THREAD_INFO'),
                'audit' => env('HOWL_DISCORD_THREAD_AUDIT'),
                'deployments' => env('HOWL_DISCORD_THREAD_DEPLOYMENTS'),
            ],

            // Per-category webhook URLs (optional progressive enhancement).
            // If set, the category bypasses threads and uses its own webhook URL.
            'channels' => [
                'errors' => env('HOWL_DISCORD_ERRORS'),
                'warnings' => env('HOWL_DISCORD_WARNINGS'),
                'info' => env('HOWL_DISCORD_INFO'),
                'audit' => env('HOWL_DISCORD_AUDIT'),
                'deployments' => env('HOWL_DISCORD_DEPLOYMENTS'),
            ],

            // HTTP timeout in seconds
            'timeout' => env('HOWL_DISCORD_TIMEOUT', 10),

            // Optional avatar URL
            'avatar_url' => env('HOWL_DISCORD_AVATAR_URL'),
        ],

        // Future drivers (v2+) — reserved scaffolding
        'telegram' => [
            'bot_token' => env('HOWL_TELEGRAM_BOT_TOKEN'),
            'chat_id' => env('HOWL_TELEGRAM_CHAT_ID'),
        ],

        'slack' => [
            'webhook_url' => env('HOWL_SLACK_WEBHOOK_URL'),
        ],
    ],

    /*
    |-------------------------------------------------------------------------
    | Severity → color (decimal)
    |-------------------------------------------------------------------------
    */
    'colors' => [
        'error' => 15548997,      // #ED4245
        'warning' => 16765440,    // #FFC000
        'info' => 3447003,        // #4169E1
        'success' => 5763719,     // #57F287
        'audit' => 10181046,      // #9B59B6
        'deployment' => 1752220,  // #1ABC9C
    ],

    /*
    |-------------------------------------------------------------------------
    | Severity → emoji
    |-------------------------------------------------------------------------
    */
    'emojis' => [
        'error' => '🚨',
        'warning' => '🟡',
        'info' => 'ℹ️',
        'success' => '✅',
        'audit' => '🔒',
        'deployment' => '🚀',
    ],

    /*
    |-------------------------------------------------------------------------
    | Mention IDs (per category)
    |-------------------------------------------------------------------------
    */
    'mentions' => [
        'errors' => env('HOWL_MENTION_ERRORS'),
        'warnings' => env('HOWL_MENTION_WARNINGS'),
        'audit' => env('HOWL_MENTION_AUDIT'),
        'deployments' => env('HOWL_MENTION_DEPLOYMENTS'),
    ],

    /*
    |-------------------------------------------------------------------------
    | Skip sending entirely when APP_ENV is in this list
    |-------------------------------------------------------------------------
    */
    'skip_environments' => ['testing'],

];
