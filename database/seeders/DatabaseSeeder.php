<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Activity;
use App\Models\Car;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Part;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $owner = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'role' => 'Owner',
                'status' => 'active',
                'password' => Hash::make('password'),
            ]
        );
        $owner->syncRoles(['Owner']);

        $manager = User::updateOrCreate(
            ['email' => 'parts@example.com'],
            [
                'name' => 'Parts Manager',
                'role' => 'Manager',
                'status' => 'active',
                'password' => Hash::make('password'),
            ]
        );
        $manager->syncRoles(['Manager']);

        $sales = User::updateOrCreate(
            ['email' => 'sales@example.com'],
            [
                'name' => 'Sales Operator',
                'role' => 'Sales',
                'status' => 'inactive',
                'password' => Hash::make('password'),
            ]
        );
        $sales->syncRoles(['Sales']);

        $cars = [
            ['vin' => 'JTDBR32E720000001', 'make' => 'Toyota', 'model' => 'Corolla', 'year' => 2018, 'color' => 'White', 'mileage' => 82000, 'status' => 'active', 'purchase_price' => 3900, 'sale_price' => 7200],
            ['vin' => 'WBA3A5C50DF000002', 'make' => 'BMW', 'model' => '328i', 'year' => 2016, 'color' => 'Black', 'mileage' => 97000, 'status' => 'pending', 'purchase_price' => 5100, 'sale_price' => 9400],
            ['vin' => 'WAUZZZ8K9AA000003', 'make' => 'Audi', 'model' => 'A4', 'year' => 2017, 'color' => 'Gray', 'mileage' => 88500, 'status' => 'active', 'purchase_price' => 5600, 'sale_price' => 10100],
        ];
        foreach ($cars as $car) {
            Car::updateOrCreate(['vin' => $car['vin']], $car);
        }

        $this->call(CarCatalogSeeder::class);
        $this->call(KiaK4Seeder::class);

        $customers = [
            ['name' => 'AutoFix Garage', 'email' => 'orders@autofix.test', 'phone' => '+1 555 0110', 'address' => '120 Market Street, Denver, CO'],
            ['name' => 'North Parts LLC', 'email' => 'buy@northparts.test', 'phone' => '+1 555 0111', 'address' => '88 Industrial Road, Newark, NJ'],
            ['name' => 'City Motors', 'email' => 'parts@citymotors.test', 'phone' => '+1 555 0112', 'address' => '41 Service Avenue, Phoenix, AZ'],
        ];
        foreach ($customers as $customer) {
            Customer::updateOrCreate(['email' => $customer['email']], $customer);
        }

        $orders = [
            ['customer_name' => 'AutoFix Garage', 'total' => 1730, 'status' => 'pending'],
            ['customer_name' => 'North Parts LLC', 'total' => 420, 'status' => 'completed'],
            ['customer_name' => 'City Motors', 'total' => 280, 'status' => 'cancelled'],
        ];
        foreach ($orders as $order) {
            Order::updateOrCreate($order, $order);
        }

        $partRows = [
            ['car_id' => 1, 'name' => 'Engine assembly', 'sku' => 'ENG-TOY-001', 'category' => 'Engine', 'condition' => 'Used', 'description' => 'Complete engine assembly removed from Toyota Corolla 2018.', 'price' => 1450, 'quantity' => 1, 'status' => 'active'],
            ['car_id' => 2, 'name' => 'Front right door', 'sku' => 'DR-BMW-014', 'category' => 'Body Parts', 'condition' => 'Good', 'description' => 'Black front passenger door with minor surface scratches.', 'price' => 420, 'quantity' => 2, 'status' => 'active'],
            ['car_id' => 3, 'name' => 'LED headlight', 'sku' => 'LGT-AUD-023', 'category' => 'Lights', 'condition' => 'Used', 'description' => 'OEM LED headlight tested before inventory intake.', 'price' => 280, 'quantity' => 4, 'status' => 'active'],
        ];
        foreach ($partRows as $part) {
            Part::updateOrCreate(['sku' => $part['sku']], $part);
        }

        $activityRows = [
            ['label' => 'Order created', 'description' => 'AutoFix Garage placed a new parts order.'],
            ['label' => 'Part stocked', 'description' => 'LED headlight quantity updated to 4.'],
            ['label' => 'Car received', 'description' => 'Audi A4 2017 moved into dismantling queue.'],
        ];
        foreach ($activityRows as $row) {
            Activity::updateOrCreate($row, $row);
        }

    }
}
