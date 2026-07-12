<?php

namespace App\Services\Inventory;

use App\Models\Car;
use App\Models\CarMake;
use App\Models\CarModel;
use App\Models\Part;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VehiclePartCatalog
{
    public function __construct(
        private readonly PartTranslationService $translations
    ) {
    }

    public function for(string $make, string $model, int $year): array
    {
        $key = $this->normalizeKey($make.'|'.$model.'|'.$year);

        return match ($key) {
            'kia|k4|2024' => $this->kiaK4_2024(),
            default => throw new InvalidArgumentException(sprintf(
                'No part catalog is defined for %s %s %d.',
                $make,
                $model,
                $year
            )),
        };
    }

    /**
     * Sync a car-specific part catalog into the database.
     *
     * Excluded values may match either SKU or part name.
     */
    public function sync(Car $car, array $parts, array $excluded = []): array
    {
        $excludedInput = collect($excluded)
            ->filter(static fn ($value) => is_string($value) && trim($value) !== '')
            ->values();

        $excluded = $excludedInput
            ->map(fn (string $value) => $this->normalizeKey($value))
            ->values();

        $created = 0;
        $updated = 0;
        $deleted = 0;
        $keptSkus = [];

        DB::transaction(function () use ($car, $parts, $excluded, &$created, &$updated, &$deleted, &$keptSkus): void {
            foreach ($parts as $part) {
                $sku = (string) $part['sku'];
                $name = (string) $part['name'];
                $translations = $this->translations->translate($name);

                if ($excluded->contains($this->normalizeKey($sku)) || $excluded->contains($this->normalizeKey($name))) {
                    continue;
                }

                $keptSkus[] = $sku;

                $model = Part::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'car_id' => $car->id,
                        'name' => $name,
                        'name_ru' => $translations['ru'],
                        'name_hy' => $translations['hy'],
                        'sku' => $sku,
                        'category' => (string) $part['category'],
                        'condition' => (string) $part['condition'],
                        'description' => $part['description'] ?? null,
                        'price' => $part['price'],
                        'quantity' => (int) ($part['quantity'] ?? 1),
                        'status' => $part['status'] ?? 'active',
                        'image_url' => $part['image_url'] ?? null,
                    ]
                );

                if ($model->wasRecentlyCreated) {
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
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deleted,
            'kept' => count($keptSkus),
            'excluded' => $excludedInput->all(),
        ];
    }

    /**
     * Resolve a make/model/year combination and return the imported car record.
     */
    public function resolveCar(string $make, string $model, int $year, array $carAttributes, ?string $vin = null): Car
    {
        $carMake = CarMake::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($make)])
            ->first();
        if (! $carMake) {
            throw new ModelNotFoundException(sprintf('Car make "%s" was not found.', $make));
        }

        $carModel = CarModel::query()
            ->where('car_make_id', $carMake->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($model)])
            ->first();

        if (! $carModel) {
            throw new ModelNotFoundException(sprintf('Car model "%s %s" was not found.', $make, $model));
        }

        $vin = $vin ?: strtoupper('IMP-'.preg_replace('/[^A-Z0-9]+/', '-', $this->normalizeKey($make.'-'.$model.'-'.$year)));
        $vin = trim($vin);

        return Car::updateOrCreate(
            ['vin' => $vin],
            array_merge(
                [
                    'car_make_id' => $carMake->id,
                    'car_model_id' => $carModel->id,
                    'make' => $carMake->name,
                    'model' => $carModel->name,
                    'year' => $year,
                ],
                $carAttributes
            )
        );
    }

    private function kiaK4_2024(): array
    {
        return [
            $this->part('K4-ENG-001', 'Engine assembly', 'Engine', 'Used', 'Complete 2024 Kia K4 engine assembly with accessories removed for dismantling.', 4200),
            $this->part('K4-ENG-002', 'Engine control module', 'Electrical', 'Used', 'ECU matched to the Kia K4 engine setup.', 520),
            $this->part('K4-TRN-001', 'Automatic transmission', 'Transmission', 'Used', 'OEM automatic transmission tested before storage.', 2850),
            $this->part('K4-TRN-002', 'Shift selector assembly', 'Transmission', 'Used', 'Transmission selector and cable assembly.', 240),
            $this->part('K4-BDY-001', 'Front bumper cover', 'Body Parts', 'Good', 'Front bumper cover with minor cosmetic wear.', 380),
            $this->part('K4-BDY-002', 'Rear bumper cover', 'Body Parts', 'Good', 'Rear bumper cover, no cracks.', 320),
            $this->part('K4-BDY-003', 'Hood', 'Body Parts', 'Used', 'Straight hood panel in factory paint.', 450),
            $this->part('K4-BDY-004', 'Trunk lid', 'Body Parts', 'Used', 'Rear trunk lid complete with trim.', 410),
            $this->part('K4-BDY-005', 'Front left door', 'Body Parts', 'Good', 'Driver-side front door complete with glass and trim.', 520),
            $this->part('K4-BDY-006', 'Front right door', 'Body Parts', 'Good', 'Passenger-side front door complete with glass and trim.', 520),
            $this->part('K4-BDY-007', 'Rear left door', 'Body Parts', 'Good', 'Rear left door complete.', 470),
            $this->part('K4-BDY-008', 'Rear right door', 'Body Parts', 'Good', 'Rear right door complete.', 470),
            $this->part('K4-BDY-009', 'Front left fender', 'Body Parts', 'Used', 'Front left fender, straight and repairable.', 260),
            $this->part('K4-BDY-010', 'Front right fender', 'Body Parts', 'Used', 'Front right fender, straight and repairable.', 260),
            $this->part('K4-BDY-011', 'Front grille', 'Body Parts', 'Used', 'Front grille assembly with emblem mount.', 210),
            $this->part('K4-BDY-012', 'Left fender liner', 'Body Parts', 'Used', 'Front wheel arch liner, left side.', 85),
            $this->part('K4-BDY-013', 'Right fender liner', 'Body Parts', 'Used', 'Front wheel arch liner, right side.', 85),
            $this->part('K4-LGT-001', 'Left headlight', 'Lights', 'Used', 'OEM left headlight assembly.', 640),
            $this->part('K4-LGT-002', 'Right headlight', 'Lights', 'Used', 'OEM right headlight assembly.', 640),
            $this->part('K4-LGT-003', 'Left tail light', 'Lights', 'Good', 'Rear left tail light assembly.', 220),
            $this->part('K4-LGT-004', 'Right tail light', 'Lights', 'Good', 'Rear right tail light assembly.', 220),
            $this->part('K4-LGT-005', 'Front fog lamp set', 'Lights', 'Used', 'Front fog light pair with bezels.', 180),
            $this->part('K4-COOL-001', 'Radiator', 'Cooling System', 'Used', 'Cooling radiator with fan shroud.', 260),
            $this->part('K4-COOL-002', 'Condenser', 'Cooling System', 'Used', 'A/C condenser removed from the Kia K4.', 180),
            $this->part('K4-COOL-003', 'Cooling fan assembly', 'Cooling System', 'Used', 'Electric radiator cooling fan assembly.', 210),
            $this->part('K4-COOL-004', 'Expansion tank', 'Cooling System', 'Used', 'Coolant expansion tank and cap.', 75),
            $this->part('K4-ELE-001', 'Alternator', 'Electrical', 'Used', 'Charging system alternator tested good.', 240),
            $this->part('K4-ELE-002', 'Starter motor', 'Electrical', 'Used', 'Starter motor in working condition.', 160),
            $this->part('K4-ELE-003', 'Infotainment screen', 'Electrical', 'Good', 'Center display and infotainment unit.', 780),
            $this->part('K4-ELE-004', 'Battery tray', 'Electrical', 'Used', 'Battery tray and hold-down bracket.', 60),
            $this->part('K4-ELE-005', 'Fuse box', 'Electrical', 'Used', 'Engine bay fuse box and cover.', 120),
            $this->part('K4-ELE-006', 'Wiring harness', 'Electrical', 'Used', 'Front body wiring harness section.', 420),
            $this->part('K4-INT-001', 'Front seat set', 'Interior', 'Good', 'Front seat pair with intact upholstery.', 680),
            $this->part('K4-INT-002', 'Steering wheel', 'Interior', 'Good', 'Original steering wheel with controls.', 240),
            $this->part('K4-INT-003', 'Dashboard trim', 'Interior', 'Used', 'Dashboard trim and center fascia pieces.', 140),
            $this->part('K4-INT-004', 'Seat belt set', 'Interior', 'Used', 'Front seat belt assemblies, pair.', 170),
            $this->part('K4-INT-005', 'Instrument cluster', 'Interior', 'Used', 'Gauge cluster assembly.', 260),
            $this->part('K4-SUS-001', 'Front strut assembly', 'Suspension', 'Used', 'Front suspension strut assembly, left and right set.', 520),
            $this->part('K4-SUS-002', 'Rear shock absorber set', 'Suspension', 'Used', 'Rear shock absorber pair.', 260),
            $this->part('K4-SUS-003', 'Control arm set', 'Suspension', 'Used', 'Front lower control arm pair.', 280),
            $this->part('K4-SUS-004', 'Steering rack', 'Suspension', 'Used', 'Power steering rack assembly.', 610),
            $this->part('K4-BRK-001', 'Front brake caliper set', 'Brake System', 'Used', 'Front brake calipers with brackets.', 300),
            $this->part('K4-BRK-002', 'Rear brake caliper set', 'Brake System', 'Used', 'Rear brake calipers with brackets.', 240),
            $this->part('K4-BRK-003', 'ABS module', 'Brake System', 'Used', 'ABS hydraulic control module.', 380),
            $this->part('K4-BRK-004', 'Brake booster', 'Brake System', 'Used', 'Vacuum brake booster and master cylinder.', 220),
            $this->part('K4-EXR-001', 'Left side mirror', 'Exterior', 'Good', 'Power side mirror with indicator.', 190),
            $this->part('K4-EXR-002', 'Right side mirror', 'Exterior', 'Good', 'Power side mirror with indicator.', 190),
            $this->part('K4-EXR-003', 'Wiper motor', 'Exterior', 'Used', 'Front windshield wiper motor assembly.', 110),
            $this->part('K4-EXR-004', 'Windshield washer pump', 'Exterior', 'Used', 'Washer pump and reservoir cap.', 55),
            $this->part('K4-EXR-005', 'Windshield glass', 'Exterior', 'Used', 'Front windshield glass, OEM.', 330),
            $this->part('K4-ENG-003', 'Air intake box', 'Engine', 'Used', 'Air filter box and intake ducting.', 95),
            $this->part('K4-ENG-004', 'Throttle body', 'Engine', 'Used', 'Electronic throttle body assembly.', 180),
            $this->part('K4-ENG-005', 'Radiator support', 'Body Parts', 'Used', 'Front radiator support panel.', 210),
            $this->part('K4-ENG-006', 'Fuel pump module', 'Fuel System', 'Used', 'In-tank fuel pump module.', 290),
            $this->part('K4-ENG-007', 'Catalytic converter', 'Exhaust', 'Used', 'OEM catalytic converter section.', 680),
            $this->part('K4-ENG-008', 'Muffler assembly', 'Exhaust', 'Used', 'Rear exhaust muffler assembly.', 240),
            $this->part('K4-ENG-009', 'Engine mount set', 'Engine', 'Used', 'Left and right engine mount brackets.', 180),
            $this->part('K4-ENG-010', 'AC compressor', 'Cooling System', 'Used', 'Air conditioning compressor.', 320),
        ];
    }

    private function part(
        string $sku,
        string $name,
        string $category,
        string $condition,
        string $description,
        int|float $price,
        int $quantity = 1
    ): array {
        return [
            'sku' => $sku,
            'name' => $name,
            'category' => $category,
            'condition' => $condition,
            'description' => $description,
            'price' => $price,
            'quantity' => $quantity,
        ];
    }

    private function normalizeKey(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '|', $value) ?? $value;

        return trim($value, '|');
    }
}
