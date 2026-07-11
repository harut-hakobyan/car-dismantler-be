<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    use RespondsWithPagination;

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = (int) $request->query('per_page', 10);
        $limit = (int) $request->query('limit', 0);
        $sort = (string) $request->query('sort', '-created_at');
        $sortColumn = 'created_at';
        $sortDirection = 'desc';

        if ($sort !== '') {
            $sortDirection = str_starts_with($sort, '-') ? 'desc' : 'asc';
            $candidate = ltrim($sort, '-');

            if (in_array($candidate, ['created_at', 'customer_name', 'status', 'total'], true)) {
                $sortColumn = $candidate;
            }
        }

        $query = Order::query();

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        $query->orderBy($sortColumn, $sortDirection);

        if ($limit > 0) {
            return response()->json(
                $query->limit($limit)->get()->map(fn (Order $order) => $this->mapOrder($order))
            );
        }

        return $this->paginated($query->paginate($perPage), fn (Order $order) => $this->mapOrder($order));
    }

    public function update(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'total' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['required', 'string', Rule::in(['pending', 'completed', 'cancelled'])],
        ]);

        $order->update($validated);

        return response()->json($this->mapOrder($order->refresh()));
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json($this->mapOrder($order));
    }

    private function mapOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'customer_name' => $order->customer_name,
            'total' => (float) $order->total,
            'status' => $order->status,
            'created_at' => optional($order->created_at)->toDateString(),
        ];
    }
}
