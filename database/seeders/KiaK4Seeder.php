<?php

namespace Database\Seeders;

use App\Models\Car;
use App\Models\CarMake;
use App\Models\CarModel;
use App\Services\Inventory\VehiclePartCatalog;
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

        $catalog = app(VehiclePartCatalog::class);
        $parts = $catalog->for('Kia', 'K4', 2024);
        $catalog->sync($car, $parts);
    }
}
