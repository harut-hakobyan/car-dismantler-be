<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarMake;
use App\Models\CarModel;
use Illuminate\Http\JsonResponse;

class CarMakeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            CarMake::query()
                ->orderBy('name')
                ->get()
                ->map(fn (CarMake $make) => [
                    'id' => $make->id,
                    'name' => $make->name,
                    'region' => $make->region,
                ])
                ->values()
        );
    }

    public function models(CarMake $carMake): JsonResponse
    {
        return response()->json(
            CarModel::query()
                ->where('car_make_id', $carMake->id)
                ->orderBy('name')
                ->get()
                ->map(fn (CarModel $model) => [
                    'id' => $model->id,
                    'car_make_id' => $model->car_make_id,
                    'name' => $model->name,
                ])
                ->values()
        );
    }
}
