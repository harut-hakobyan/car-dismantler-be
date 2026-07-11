<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Car;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Part;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        return response()->json([
            'cars' => Car::count(),
            'parts' => Part::count(),
            'orders' => Order::count(),
            'customers' => Customer::count(),
            'inventoryValue' => (float) Part::query()->selectRaw('COALESCE(SUM(price * quantity), 0) as total')->value('total'),
            'pendingOrders' => Order::where('status', 'pending')->count(),
        ]);
    }

    public function details(): JsonResponse
    {
        return response()->json([
            'orderRevenue' => (float) Order::where('status', 'completed')->sum('total'),
            'averageOrderValue' => Order::count() > 0 ? (float) Order::avg('total') : 0,
            'lowStockParts' => Part::where('quantity', '<=', 2)
                ->orderBy('quantity')
                ->get()
                ->map(fn (Part $part) => $this->mapPart($part))
                ->values(),
            'recentCars' => Car::orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(fn (Car $car) => $this->mapCar($car))
                ->values(),
            'topParts' => Part::orderByRaw('price * quantity desc')
                ->limit(5)
                ->get()
                ->map(fn (Part $part) => $this->mapPart($part))
                ->values(),
            'orderStatusCounts' => collect(['pending', 'completed', 'cancelled'])->map(fn (string $status) => [
                'status' => $status,
                'count' => Order::where('status', $status)->count(),
            ])->values(),
            'carStatusCounts' => collect(['active', 'pending', 'inactive'])->map(fn (string $status) => [
                'status' => $status,
                'count' => Car::where('status', $status)->count(),
            ])->values(),
        ]);
    }

    public function activity(): JsonResponse
    {
        return response()->json(
            Activity::orderBy('created_at', 'desc')
                ->limit(20)
                ->get()
                ->map(fn (Activity $activity) => [
                    'id' => $activity->id,
                    'label' => $activity->label,
                    'description' => $activity->description,
                    'created_at' => optional($activity->created_at)->format('Y-m-d H:i'),
                ])
                ->values()
        );
    }

    public function recentOrders(): JsonResponse
    {
        return response()->json(
            Order::orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(fn (Order $order) => [
                    'id' => $order->id,
                    'customer_name' => $order->customer_name,
                    'total' => (float) $order->total,
                    'status' => $order->status,
                    'created_at' => optional($order->created_at)->toDateString(),
                ])
                ->values()
        );
    }

    private function mapCar(Car $car): array
    {
        return [
            'id' => $car->id,
            'vin' => $car->vin,
            'make' => $car->make,
            'model' => $car->model,
            'year' => $car->year,
            'color' => $car->color,
            'mileage' => $car->mileage,
            'status' => $car->status,
            'purchase_price' => (float) $car->purchase_price,
            'sale_price' => (float) $car->sale_price,
            'created_at' => optional($car->created_at)->toDateString(),
        ];
    }

    private function mapPart(Part $part): array
    {
        return [
            'id' => $part->id,
            'car_id' => $part->car_id,
            'name' => $part->name,
            'sku' => $part->sku,
            'category' => $part->category,
            'condition' => $part->condition,
            'description' => $part->description ?? '',
            'price' => (float) $part->price,
            'quantity' => $part->quantity,
            'status' => $part->status,
            'image_url' => $part->image_url,
        ];
    }
}
