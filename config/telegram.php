<?php

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    'allowed_user_ids' => array_values(array_filter(array_map(
        static fn (string $value): string => trim($value),
        explode(',', (string) env('TELEGRAM_ALLOWED_USER_IDS', ''))
    ))),
];
