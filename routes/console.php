<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('telegram:set-webhook', function () {
    $token = config('telegram.bot_token');
    $secret = config('telegram.webhook_secret');

    if (! $token) {
        $this->error('TELEGRAM_BOT_TOKEN is not set.');
        return;
    }

    $response = Http::timeout(15)->post("https://api.telegram.org/bot{$token}/setWebhook", [
        'url' => route('telegram.webhook'),
        'secret_token' => $secret,
        'drop_pending_updates' => true,
    ]);

    if (! $response->successful()) {
        $this->error('Failed to set webhook.');
        $this->line($response->body());
        return;
    }

    $this->info('Telegram webhook configured.');
})->purpose('Register the Telegram webhook endpoint');
