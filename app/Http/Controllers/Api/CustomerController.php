<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    use RespondsWithPagination;

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = (int) $request->query('per_page', 10);
        $query = Customer::query()->orderBy('id', 'desc');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        return $this->paginated($query->paginate($perPage), fn (Customer $customer) => $this->mapCustomer($customer));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateCustomer($request);
        $customer = Customer::create($validated);

        return response()->json($this->mapCustomer($customer), 201);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validated = $this->validateCustomer($request, $customer->id);
        $customer->update($validated);

        return response()->json($this->mapCustomer($customer->refresh()));
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return response()->json(['message' => 'Customer deleted']);
    }

    private function validateCustomer(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($ignoreId)],
            'phone' => ['required', 'string', 'max:60'],
            'address' => ['required', 'string', 'max:255'],
        ]);
    }

    private function mapCustomer(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'address' => $customer->address,
        ];
    }
}
