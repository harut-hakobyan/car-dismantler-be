<?php

namespace App\Services\Inventory;

use App\Models\Car;
use App\Models\CarMake;
use App\Models\CarModel;
use App\Models\Part;
use App\Models\PartTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PartCatalogService
{
    public function __construct(
        private readonly PartTranslationService $translations
    ) {
    }

    public function seedFromSevenZapSnapshot(
        string $htmlPath,
        ?string $make = null,
        ?string $model = null,
        ?int $startYear = null,
        ?int $endYear = null,
        ?string $region = null
    ): array {
        $snapshot = $this->parseSnapshot($htmlPath);

        $make ??= $snapshot['make'];
        $model ??= $snapshot['model'];
        $startYear ??= $snapshot['start_year'];
        $endYear ??= $snapshot['end_year'] ?? $startYear;

        if (! $make || ! $model || ! $startYear) {
            throw new \RuntimeException('Could not determine make, model, and year range from the snapshot. Pass them explicitly.');
        }

        $carMake = $this->resolveMake($make, $region);
        $carModel = $this->resolveModel($carMake, $model);

        $created = 0;
        $updated = 0;

        foreach ($snapshot['items'] as $item) {
            $translations = $this->translations->translate($item['name']);

            $template = PartTemplate::updateOrCreate(
                ['sku' => $item['sku']],
                [
                    'car_make_id' => $carMake->id,
                    'car_model_id' => $carModel->id,
                    'start_year' => $startYear,
                    'end_year' => $endYear,
                    'name' => $item['name'],
                    'name_ru' => $translations['ru'],
                    'name_hy' => $translations['hy'],
                    'category' => $item['category'],
                    'path' => $item['path'],
                    'url' => $item['url'],
                    'image_url' => $item['image'],
                    'sort_order' => $item['position'],
                ]
            );

            if ($template->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        return [
            'car_make' => $carMake->name,
            'car_model' => $carModel->name,
            'start_year' => $startYear,
            'end_year' => $endYear,
            'items' => count($snapshot['items']),
            'created' => $created,
            'updated' => $updated,
        ];
    }

    public function previewForCar(Car $car): Collection
    {
        return PartTemplate::query()
            ->where('car_make_id', $car->car_make_id)
            ->where('car_model_id', $car->car_model_id)
            ->where(function ($query) use ($car) {
                $query->whereNull('start_year')
                    ->orWhere('start_year', '<=', $car->year);
            })
            ->where(function ($query) use ($car) {
                $query->whereNull('end_year')
                    ->orWhere('end_year', '>=', $car->year);
            })
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get();
    }

    public function attachToCar(Car $car, array $excludedNames = []): array
    {
        $excluded = collect($excludedNames)
            ->filter(static fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => Str::lower(trim($value)))
            ->values()
            ->all();

        $templates = $this->previewForCar($car);

        if ($templates->isEmpty()) {
            return [
                'created' => 0,
                'updated' => 0,
                'deleted' => 0,
                'kept' => 0,
            ];
        }

        $created = 0;
        $updated = 0;
        $deleted = 0;
        $attachedSkus = [];

        DB::transaction(function () use ($car, $templates, $excluded, &$created, &$updated, &$deleted, &$attachedSkus): void {
            foreach ($templates as $template) {
                $translatedExcluded = [
                    Str::lower($template->sku),
                    Str::lower($template->name),
                ];

                if (in_array($translatedExcluded[0], $excluded, true) || in_array($translatedExcluded[1], $excluded, true)) {
                    continue;
                }

                $carSku = $this->carSku($car, $template);
                $attachedSkus[] = $carSku;

                $translations = ($template->name_ru && $template->name_hy)
                    ? ['ru' => $template->name_ru, 'hy' => $template->name_hy]
                    : $this->translations->translate($template->name);

                $part = Part::updateOrCreate(
                    ['sku' => $carSku],
                    [
                        'car_id' => $car->id,
                        'name' => $template->name,
                        'name_ru' => $translations['ru'],
                        'name_hy' => $translations['hy'],
                        'sku' => $carSku,
                        'category' => $template->category,
                        'condition' => 'Catalog',
                        'description' => $template->path,
                        'price' => 0,
                        'quantity' => 1,
                        'status' => 'active',
                        'image_url' => $template->image_url,
                    ]
                );

                if ($part->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            }

            $query = Part::query()->where('car_id', $car->id);
            if ($attachedSkus !== []) {
                $deleted = (clone $query)->whereNotIn('sku', $attachedSkus)->count();
                $query->whereNotIn('sku', $attachedSkus)->delete();
            } else {
                $deleted = (clone $query)->count();
                $query->delete();
            }
        });

        return [
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deleted,
            'kept' => count($attachedSkus),
        ];
    }

    private function carSku(Car $car, PartTemplate $template): string
    {
        return Str::upper(Str::limit(sprintf('CAR-%d-%s', $car->id, $template->sku), 60, ''));
    }

    private function resolveMake(string $make, ?string $region): CarMake
    {
        return CarMake::query()->firstOrCreate(
            ['name' => $make],
            ['slug' => Str::slug($make), 'region' => $region]
        );
    }

    private function resolveModel(CarMake $make, string $model): CarModel
    {
        return CarModel::query()->firstOrCreate(
            ['car_make_id' => $make->id, 'slug' => Str::slug($model)],
            ['name' => $model]
        );
    }

    private function parseSnapshot(string $htmlPath): array
    {
        $html = file_get_contents($htmlPath);
        if ($html === false || trim($html) === '') {
            throw new \RuntimeException(sprintf('Unable to read HTML file: %s', $htmlPath));
        }

        if (! preg_match('/<title>(.*?)<\/title>/si', $html, $titleMatches)) {
            throw new \RuntimeException('Could not read snapshot title.');
        }

        $title = html_entity_decode(trim(strip_tags($titleMatches[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (! preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/si', $html, $jsonMatches)) {
            throw new \RuntimeException('Could not locate 7zap structured data in the HTML snapshot.');
        }

        $data = json_decode(html_entity_decode(trim($jsonMatches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true, flags: JSON_THROW_ON_ERROR);
        $itemList = collect($data['@graph'] ?? [])->firstWhere('@type', 'ItemList');
        if (! $itemList) {
            throw new \RuntimeException('7zap structured data does not contain an ItemList.');
        }

        [$make, $model, $startYear, $endYear] = $this->parseVehicleFromTitle($title);

        $items = [];
        foreach ($itemList['itemListElement'] ?? [] as $entry) {
            $url = (string) data_get($entry, 'item.url', '');
            $name = (string) data_get($entry, 'item.name', '');
            if ($url === '' || $name === '') {
                continue;
            }

            $parts = array_values(array_filter(array_map(
                static fn (string $segment) => trim($segment),
                preg_split('/\s*[›>]\s*/u', $name) ?: []
            )));

            $partName = array_pop($parts) ?? $name;
            $translations = $this->translations->translate($partName);
            $path = implode(' / ', array_merge([$make, $model], $parts, [$partName]));

            $items[] = [
                'position' => (int) ($entry['position'] ?? 0),
                'name' => $partName,
                'name_ru' => $translations['ru'],
                'name_hy' => $translations['hy'],
                'path' => $path,
                'sku' => $this->buildSku($url, (int) ($entry['position'] ?? 0)),
                'category' => $parts !== [] ? implode(' / ', $parts) : 'Catalog',
                'url' => $url,
                'image' => data_get($entry, 'item.image'),
            ];
        }

        return [
            'title' => $title,
            'make' => $make,
            'model' => $model,
            'start_year' => $startYear,
            'end_year' => $endYear,
            'items' => $items,
        ];
    }

    private function parseVehicleFromTitle(string $title): array
    {
        if (preg_match('/^(.*?)\s+(\d{4})-(\d{4})\b/s', $title, $matches)) {
            $makeAndModel = trim($matches[1]);
            $startYear = (int) $matches[2];
            $endYear = (int) $matches[3];

            $make = null;
            $model = $makeAndModel;
            if (preg_match('/^([A-Za-z]+)\s+(.+)$/', $makeAndModel, $vehicleMatches)) {
                $make = trim($vehicleMatches[1]);
                $model = trim($vehicleMatches[2]);
            }

            return [$make, $model, $startYear, $endYear];
        }

        return [null, null, null, null];
    }

    private function buildSku(string $url, int $position): string
    {
        $slug = Str::slug(trim(parse_url($url, PHP_URL_PATH) ?: $url, '/'));
        $hash = substr(sha1($url), 0, 8);

        return Str::upper(Str::limit(sprintf('7ZAP-%04d-%s-%s', $position, $slug, $hash), 60, ''));
    }
}
