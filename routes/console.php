<?php

use App\Services\Inventory\VehiclePartCatalog;
use App\Services\Inventory\PartCatalogService;
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

Artisan::command('inventory:import-car-parts {make} {model} {year} {--vin=} {--color=Gray} {--mileage=0} {--purchase-price=0} {--sale-price=0} {--status=active} {--exclude=*}', function () {
    $make = trim((string) $this->argument('make'));
    $model = trim((string) $this->argument('model'));
    $year = (int) $this->argument('year');

    $catalog = app(VehiclePartCatalog::class);
    $parts = $catalog->for($make, $model, $year);
    $car = $catalog->resolveCar($make, $model, $year, [
        'color' => (string) $this->option('color'),
        'mileage' => (int) $this->option('mileage'),
        'status' => (string) $this->option('status'),
        'purchase_price' => (float) $this->option('purchase-price'),
        'sale_price' => (float) $this->option('sale-price'),
    ], $this->option('vin') ? trim((string) $this->option('vin')) : null);

    $excluded = collect((array) $this->option('exclude'))
        ->filter(static fn ($value) => is_string($value) && trim($value) !== '')
        ->values()
        ->all();

    $result = $catalog->sync($car, $parts, $excluded);

    $this->info(sprintf(
        'Imported %d parts for %s %s %d (created: %d, updated: %d, deleted: %d).',
        $result['kept'],
        $make,
        $model,
        $year,
        $result['created'],
        $result['updated'],
        $result['deleted']
    ));

    if ($result['excluded'] !== []) {
        $this->line('Excluded: '.implode(', ', $result['excluded']));
    }
})->purpose('Create or update a car and sync its parts catalog, excluding damaged parts');

Artisan::command('inventory:import-7zap-html {file} {--make=} {--model=} {--start-year=} {--end-year=} {--region=}', function () {
    $file = (string) $this->argument('file');

    $result = app(PartCatalogService::class)->seedFromSevenZapSnapshot(
        $file,
        $this->option('make') ?: null,
        $this->option('model') ?: null,
        $this->option('start-year') ? (int) $this->option('start-year') : null,
        $this->option('end-year') ? (int) $this->option('end-year') : null,
        $this->option('region') ?: null
    );

    $this->info(sprintf(
        'Seeded %d catalog entries for %s %s (%d-%d) (created: %d, updated: %d).',
        $result['items'],
        $result['car_make'],
        $result['car_model'],
        $result['start_year'],
        $result['end_year'],
        $result['created'],
        $result['updated']
    ));
})->purpose('Seed reusable part catalog entries from a saved 7zap HTML snapshot');
