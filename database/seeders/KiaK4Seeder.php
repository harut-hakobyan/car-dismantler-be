<?php

namespace Database\Seeders;

use App\Models\Car;
use App\Models\CarMake;
use App\Models\CarModel;
use App\Models\Part;
use Illuminate\Database\Seeder;

class KiaK4Seeder extends Seeder
{
    public function run(): void
    {
        $make = CarMake::query()->where('name', 'Kia')->firstOrFail();
        $model = CarModel::query()
            ->where('car_make_id', $make->id)
            ->where('name', 'K4')
            ->firstOrFail();

        $car = Car::updateOrCreate(
            ['vin' => 'KNAK4-2024-000001'],
            [
                'car_make_id' => $make->id,
                'car_model_id' => $model->id,
                'make' => $make->name,
                'model' => $model->name,
                'year' => 2024,
                'color' => 'Gray',
                'mileage' => 4200,
                'status' => 'active',
                'purchase_price' => 18200,
                'sale_price' => 24900,
            ]
        );

        $parts = [
            [
                'sku' => 'K4-ENG-001',
                'name' => 'Engine assembly',
                'category' => 'Engine',
                'condition' => 'Used',
                'description' => 'Complete 2024 Kia K4 engine assembly with accessories removed for dismantling.',
                'price' => 4200,
                'quantity' => 1,
            ],
            [
                'sku' => 'K4-TRN-001',
                'name' => 'Automatic transmission',
                'category' => 'Transmission',
                'condition' => 'Used',
                'description' => 'OEM automatic transmission tested before storage.',
                'price' => 2850,
                'quantity' => 1,
            ],
            [
                'sku' => 'K4-BDY-001',
                'name' => 'Front bumper cover',
                'category' => 'Body Parts',
                'condition' => 'Good',
                'description' => 'Front bumper cover with minor cosmetic wear.',
                'price' => 380,
                'quantity' => 1,
            ],
            [
                'sku' => 'K4-BDY-002',
                'name' => 'Rear bumper cover',
                'category' => 'Body Parts',
                'condition' => 'Good',
                'description' => 'Rear bumper cover, no cracks.',
                'price' => 320,
                'quantity' => 1,
            ],
            [
                'sku' => 'K4-BDY-003',
                'name' => 'Hood',
                'category' => 'Body Parts',
                'condition' => 'Used',
                'description' => 'Straight hood panel in factory paint.',
                'price' => 450,
                'quantity' => 1,
            ],
            [
                'sku' => 'K4-BDY-004',
                'name' => 'Trunk lid',
                'category' => 'Body Parts',
                'condition' => 'Used',
                'description' => 'Rear trunk lid complete with trim.',
                'price' => 410,
                'quantity' => 1,
            ],
            [
                'sku' => 'K4-BDY-005',
                'name' => 'Front left door',
                'category' => 'Body Parts',
                'condition' => 'Good',
                'description' => 'Driver-side front door complete with glass and trim.',
                'price' => 520,
                'quantity' => 1,
            ],
            [
                'sku' => 'K4-BDY-006',
                'name' => 'Front right door',
                'category' => 'Body Parts',
                'condition' => 'Good',
                'description' => 'Passenger-side front door complete with glass and trim.',
                'price' => 520,
                'quantity' => 1,
            ],
            [
                'sku' => 'K4-BDY-007',
                'name' => 'Rear left door',
                'category' => 'Body Parts',
                'condition' => 'Good',
                'description' => 'Rear left door complete.',
                'price' => 470,
                'quantity' => 1,
            ],
            [
                'sku' => 'K4-BDY-008',
                'name' => 'Rear right door',
                'category' => 'Body Parts',
                'condition' => 'Good',
                'description' => 'Rear right door complete.',
                'price' => 470,
                'quantity' => 1,
            ],
            [
                'sku' => 'K4-BDY-009',
                'name' => 'Front left fender',
                'category' => 'Body Parts',
                'condition' => 'Used',
                'description' => 'Front left fender, straight and repairable.',
                'price' => 260,
                'quantity' => 1,
            ],
            [
                'sku' => 'K4-BDY-010',
                'name' => 'Front right fender',
                'category' => 'Body Parts',
                'condition' => 'Used',
                'description' => 'Front right fender, straight and repairable.',
                'price' => 260,
                'quantity' => 1,
            ],
            [
                'sku' => 'K4-LGT-001',
                'name' => 'Left headlight',
                'category' => 'Lights',
                'condition' => 'Used',
                'description' => 'OEM left headlight assembly.',
                'price' => 640,
                'quantity' => 1,
            ],
            [
                'sku' => 'K4-LGT-002',
                'name' => 'Right headlight',
                'category' => 'Lights',
                'condition' => 'Used',
                'description' => 'OEM right headlight assembly.',
                'price' => 640,
                'quantity' => 1,
            ],
            [
                'sku' => 'K4-LGT-003',
                'name' => 'Left tail light',
                'category' => 'Lights',
                'condition' => 'Good',
                'description' => 'Rear left tail light assembly.',
                'price' => 220,
                'quantity' => 1,
            ],
            [
                'sku' => 'K4-LGT-004',
                'name' => 'Right tail light',
                'category' => 'Lights',
                'condition' => 'Good',
                'description' => 'Rear right tail light assembly.',
                'price' => 220,
                'quantity' => 1,
            ],
            [
                'sku' => 'K4-COOL-001',
                'name' => 'Radiator',
                'category' => 'Cooling System',
                'condition' => 'Used',
                'description' => 'Cooling radiator with fan shroud.',
                'price' => 260,
                'quantity' => 1,
            ],
            [
                'sku' => 'K4-COOL-002',
                'name' => 'Condenser',
                'category' => 'Cooling System',
                'condition' => 'Used',
                'description' => 'A/C condenser removed from the Kia K4.',
                'price' => 180,
                'quantity' => 1,
            ],
            [
                'sku' => 'K4-ELE-001',
                'name' => 'Alternator',
                'category' => 'Electrical',
                'condition' => 'Used',
                'description' => 'Charging system alternator tested good.',
                'price' => 240,
                'quantity' => 1,
            ],
            [
                'sku' => 'K4-ELE-002',
                'name' => 'Starter motor',
                'category' => 'Electrical',
                'condition' => 'Used',
                'description' => 'Starter motor in working condition.',
                'price' => 160,
                'quantity' => 1,
            ],
            [
                'sku' => 'K4-ELE-003',
                'name' => 'Infotainment screen',
                'category' => 'Electrical',
                'condition' => 'Good',
                'description' => 'Center display and infotainment unit.',
                'price' => 780,
                'quantity' => 1,
            ],
            [
                'sku' => 'K4-INT-001',
                'name' => 'Front seat set',
                'category' => 'Interior',
                'condition' => 'Good',
                'description' => 'Front seat pair with intact upholstery.',
                'price' => 680,
                'quantity' => 1,
            ],
            [
                'sku' => 'K4-INT-002',
                'name' => 'Steering wheel',
                'category' => 'Interior',
                'condition' => 'Good',
                'description' => 'Original steering wheel with controls.',
                'price' => 240,
                'quantity' => 1,
            ],
            [
                'sku' => 'K4-SUS-001',
                'name' => 'Front strut assembly',
                'category' => 'Suspension',
                'condition' => 'Used',
                'description' => 'Front suspension strut assembly, left and right set.',
                'price' => 520,
                'quantity' => 1,
            ],
            [
                'sku' => 'K4-BRK-001',
                'name' => 'Front brake caliper set',
                'category' => 'Brake System',
                'condition' => 'Used',
                'description' => 'Front brake calipers with brackets.',
                'price' => 300,
                'quantity' => 1,
            ],
            [
                'sku' => 'K4-EXR-001',
                'name' => 'Left side mirror',
                'category' => 'Exterior',
                'condition' => 'Good',
                'description' => 'Power side mirror with indicator.',
                'price' => 190,
                'quantity' => 1,
            ],
            [
                'sku' => 'K4-EXR-002',
                'name' => 'Right side mirror',
                'category' => 'Exterior',
                'condition' => 'Good',
                'description' => 'Power side mirror with indicator.',
                'price' => 190,
                'quantity' => 1,
            ],
            [
                'sku' => 'K4-ENG-002',
                'name' => 'Engine control module',
                'category' => 'Electrical',
                'condition' => 'Used',
                'description' => 'ECU matched to the Kia K4 engine setup.',
                'price' => 520,
                'quantity' => 1,
            ],
        ];

        foreach ($parts as $part) {
            Part::updateOrCreate(
                ['sku' => $part['sku']],
                [
                    ...$part,
                    'car_id' => $car->id,
                    'status' => 'active',
                    'image_url' => null,
                ]
            );
        }
    }
}
