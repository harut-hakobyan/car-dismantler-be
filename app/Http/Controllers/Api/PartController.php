<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Order;
use App\Models\Part;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PartController extends Controller
{
    use RespondsWithPagination;

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = (int) $request->query('per_page', 10);
        $query = Part::query()->orderBy('id', 'desc');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('condition', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        return $this->paginated($query->paginate($perPage), fn (Part $part) => $this->mapPart($part));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePart($request);
        $part = Part::create($validated);

        return response()->json($this->mapPart($part), 201);
    }

    public function update(Request $request, Part $part): JsonResponse
    {
        $validated = $this->validatePart($request, $part->id);
        $part->update($validated);

        return response()->json($this->mapPart($part->refresh()));
    }

    public function destroy(Part $part): JsonResponse
    {
        $part->delete();

        return response()->json(['message' => 'Part deleted']);
    }

    public function sell(Request $request, Part $part): JsonResponse
    {
        $validated = $request->validate([
            'part_id' => ['required', 'integer', Rule::in([$part->id])],
            'customer_name' => ['required', 'string', 'min:2', 'max:120'],
            'quantity' => ['required', 'integer', 'min:1'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);

        if ($validated['quantity'] > $part->quantity) {
            return response()->json([
                'message' => 'Not enough quantity available',
            ], 422);
        }

        $remaining = $part->quantity - $validated['quantity'];
        $part->update([
            'quantity' => $remaining,
            'status' => $remaining === 0 ? 'inactive' : $part->status,
        ]);

        $total = round($validated['sale_price'] * $validated['quantity'], 2);

        Order::create([
            'customer_name' => $validated['customer_name'],
            'total' => $total,
            'status' => 'completed',
        ]);

        Activity::create([
            'label' => 'Part sold',
            'description' => sprintf(
                '%s x%s sold to %s for $%s.',
                $part->name,
                $validated['quantity'],
                $validated['customer_name'],
                number_format($total, 2)
            ),
        ]);

        return response()->json($this->mapPart($part->refresh()));
    }

    public function options(Request $request): JsonResponse
    {
        $sellable = filter_var($request->query('sellable', false), FILTER_VALIDATE_BOOL);
        $query = Part::query()->orderBy('name');

        if ($sellable) {
            $query->where('quantity', '>', 0);
        }

        return response()->json(
            $query->get()->map(fn (Part $part) => $sellable
                ? [
                    'id' => $part->id,
                    'label' => sprintf('%s - %s', $part->name, $part->sku),
                    'sku' => $part->sku,
                    'price' => (float) $part->price,
                    'quantity' => $part->quantity,
                ]
                : $this->mapPart($part))
        );
    }

    private function validatePart(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'car_id' => ['required', 'integer', 'exists:cars,id'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'sku' => ['required', 'string', 'max:60', Rule::unique('parts', 'sku')->ignore($ignoreId)],
            'category' => ['required', 'string', 'max:120'],
            'condition' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive', 'pending'])],
            'image_url' => ['nullable', 'string', 'max:255'],
        ]);
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
