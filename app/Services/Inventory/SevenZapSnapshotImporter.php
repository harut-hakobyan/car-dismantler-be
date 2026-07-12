<?php

namespace App\Services\Inventory;

use App\Models\Car;
use App\Models\CarMake;
use App\Models\CarModel;
use App\Models\Part;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class SevenZapSnapshotImporter
{
    public function __construct(
        private readonly PartTranslationService $translations
    ) {
    }

    public function import(
        string $htmlPath,
        array $excluded = [],
        ?string $make = null,
        ?string $model = null,
        ?int $year = null,
        ?string $vin = null,
        ?string $region = null
    ): array {
        if (! is_file($htmlPath)) {
            throw new InvalidArgumentException(sprintf('HTML file not found: %s', $htmlPath));
        }

        $html = file_get_contents($htmlPath);
        if ($html === false || trim($html) === '') {
            throw new RuntimeException(sprintf('Unable to read HTML file: %s', $htmlPath));
        }

        $snapshot = $this->parseSnapshot($html);

        $make ??= $snapshot['make'];
        $model ??= $snapshot['model'];
        $year ??= $snapshot['year'];

        if (! $make || ! $model || ! $year) {
            throw new RuntimeException('Could not determine make, model, and year from the snapshot. Pass them explicitly.');
        }

        $car = $this->resolveCar($make, $model, $year, $vin, $region);
        $excludedInput = collect($excluded)
            ->filter(static fn ($value) => is_string($value) && trim($value) !== '')
            ->values();

        $excludedNormalized = $excludedInput
            ->map(fn (string $value) => $this->normalize($value))
            ->all();

        $created = 0;
        $updated = 0;
        $deleted = 0;
        $keptSkus = [];

        DB::transaction(function () use ($car, $snapshot, $excludedNormalized, &$created, &$updated, &$deleted, &$keptSkus): void {
            foreach ($snapshot['items'] as $item) {
                $name = $item['part_name'];
                $path = $item['path'];
                $translations = $this->translations->translate($name);
                $normalizedName = $this->normalize($name);
                $normalizedPath = $this->normalize($path);

                if (in_array($normalizedName, $excludedNormalized, true) || in_array($normalizedPath, $excludedNormalized, true)) {
                    continue;
                }

                $sku = $this->buildSku($item['url'], $item['position']);
                $keptSkus[] = $sku;

                $part = Part::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'car_id' => $car->id,
                        'name' => $name,
                        'name_ru' => $translations['ru'],
                        'name_hy' => $translations['hy'],
                        'sku' => $sku,
                        'category' => $item['category'],
                        'condition' => 'Catalog',
                        'description' => '7zap catalog entry: '.$path,
                        'price' => 0,
                        'quantity' => 1,
                        'status' => 'active',
                        'image_url' => $item['image'] ?? null,
                    ]
                );

                if ($part->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            }

            $query = Part::query()->where('car_id', $car->id);

            if ($keptSkus !== []) {
                $deleted = (clone $query)->whereNotIn('sku', $keptSkus)->count();
                $query->whereNotIn('sku', $keptSkus)->delete();
            } else {
                $deleted = (clone $query)->count();
                $query->delete();
            }
        });

        return [
            'car' => $car,
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deleted,
            'kept' => count($keptSkus),
            'excluded' => $excludedInput->all(),
            'snapshot' => $snapshot,
        ];
    }

    private function parseSnapshot(string $html): array
    {
        $title = null;
        if (preg_match('/<title>(.*?)<\/title>/si', $html, $matches)) {
            $title = html_entity_decode(trim(strip_tags($matches[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (! preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/si', $html, $matches)) {
            throw new RuntimeException('Could not locate 7zap structured data in the HTML snapshot.');
        }

        $json = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        $graph = $data['@graph'] ?? [];
        $itemList = null;
        foreach ($graph as $entry) {
            if (($entry['@type'] ?? null) === 'ItemList') {
                $itemList = $entry;
                break;
            }
        }

        if (! $itemList) {
            throw new RuntimeException('7zap structured data does not contain an ItemList.');
        }

        $items = [];
        foreach ($itemList['itemListElement'] ?? [] as $entry) {
            $url = (string) data_get($entry, 'item.url', '');
            $name = (string) data_get($entry, 'item.name', '');
            if ($url === '' || $name === '') {
                continue;
            }

            $segments = array_values(array_filter(array_map(
                static fn (string $segment) => trim($segment),
                preg_split('/\s*[›>]\s*/u', $name) ?: []
            )));

            $partName = array_pop($segments) ?? $name;
            $category = $segments !== [] ? implode(' / ', $segments) : 'Catalog';

            $items[] = [
                'position' => (int) ($entry['position'] ?? 0),
                'name' => $name,
                'path' => $name,
                'part_name' => $partName,
                'category' => $category,
                'url' => $url,
                'image' => data_get($entry, 'item.image'),
            ];
        }

        [$make, $model, $year] = $this->parseVehicleFromTitle($title ?? '');

        return [
            'title' => $title,
            'make' => $make,
            'model' => $model,
            'year' => $year,
            'items' => $items,
            'numberOfItems' => (int) ($itemList['numberOfItems'] ?? count($items)),
        ];
    }

    private function parseVehicleFromTitle(string $title): array
    {
        if (preg_match('/^(.*?)\s+(\d{4})-(\d{4})\b/s', $title, $matches)) {
            $makeAndModel = trim($matches[1]);
            $startYear = (int) $matches[2];

            $model = $makeAndModel;
            $make = null;
            if (preg_match('/^([A-Za-z]+)\s+(.+)$/', $makeAndModel, $vehicleMatches)) {
                $make = trim($vehicleMatches[1]);
                $model = trim($vehicleMatches[2]);
            }

            return [$make, $model, $startYear];
        }

        return [null, null, null];
    }

    private function resolveCar(string $make, string $model, int $year, ?string $vin, ?string $region): Car
    {
        $makeModel = CarMake::query()->firstOrCreate(
            ['name' => $make],
            ['slug' => Str::slug($make), 'region' => $region]
        );

        $carModel = CarModel::query()->firstOrCreate(
            ['car_make_id' => $makeModel->id, 'slug' => Str::slug($model)],
            ['name' => $model]
        );

        $vin = $vin ?: sprintf('7ZAP-%s-%s-%d', Str::slug($make), Str::slug($model), $year);

        return Car::updateOrCreate(
            ['vin' => $vin],
            [
                'car_make_id' => $makeModel->id,
                'car_model_id' => $carModel->id,
                'make' => $makeModel->name,
                'model' => $carModel->name,
                'year' => $year,
                'color' => 'Unknown',
                'mileage' => 0,
                'status' => 'active',
                'purchase_price' => 0,
                'sale_price' => 0,
            ]
        );
    }

    private function buildSku(string $url, int $position): string
    {
        $slug = Str::slug(trim(parse_url($url, PHP_URL_PATH) ?: $url, '/'));
        $hash = substr(sha1($url), 0, 8);

        return Str::upper(Str::limit(sprintf('7ZAP-%04d-%s-%s', $position, $slug, $hash), 60, ''));
    }

    private function normalize(string $value): string
    {
        $value = Str::lower(trim($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
