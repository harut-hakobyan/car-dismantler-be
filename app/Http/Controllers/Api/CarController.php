<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarMake;
use App\Models\CarModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CarController extends Controller
{
    use RespondsWithPagination;

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = (int) $request->query('per_page', 10);
        $query = Car::query()->orderBy('id', 'desc');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('vin', 'like', "%{$search}%")
                    ->orWhere('make', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('color', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        return $this->paginated($query->paginate($perPage), fn (Car $car) => $this->mapCar($car));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateCar($request);
        $car = $this->persistCar(new Car(), $validated);

        return response()->json($this->mapCar($car), 201);
    }

    public function update(Request $request, Car $car): JsonResponse
    {
        $validated = $this->validateCar($request, $car->id);
        $car = $this->persistCar($car, $validated);

        return response()->json($this->mapCar($car->refresh()));
    }

    public function destroy(Car $car): JsonResponse
    {
        $car->delete();

        return response()->json(['message' => 'Car deleted']);
    }

    public function options(): JsonResponse
    {
        return response()->json(
            Car::query()
                ->orderBy('year', 'desc')
                ->orderBy('id', 'desc')
                ->get()
                ->map(fn (Car $car) => [
                    'id' => $car->id,
                    'label' => sprintf('%s %s %s - %s', $car->year, $car->make, $car->model, $car->vin),
                ])
        );
    }

    private function validateCar(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'vin' => ['required', 'string', 'max:120', Rule::unique('cars', 'vin')->ignore($ignoreId)],
            'car_make_id' => ['required', 'integer', 'exists:car_makes,id'],
            'car_model_id' => ['required', 'integer', 'exists:car_models,id'],
            'year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'color' => ['required', 'string', 'max:120'],
            'mileage' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive', 'pending'])],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
        ]);
    }

    private function persistCar(Car $car, array $validated): Car
    {
        $make = CarMake::findOrFail($validated['car_make_id']);
        $model = CarModel::query()
            ->where('id', $validated['car_model_id'])
            ->where('car_make_id', $make->id)
            ->firstOrFail();

        $car->fill([
            ...$validated,
            'make' => $make->name,
            'model' => $model->name,
        ]);
        $car->save();

        return $car;
    }

    private function mapCar(Car $car): array
    {
        return [
            'id' => $car->id,
            'vin' => $car->vin,
            'car_make_id' => $car->car_make_id,
            'car_model_id' => $car->car_model_id,
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
}
