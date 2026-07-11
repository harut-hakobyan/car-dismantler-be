<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

trait RespondsWithPagination
{
    protected function paginated(LengthAwarePaginator $paginator, ?callable $transform = null): JsonResponse
    {
        $items = $transform
            ? $paginator->getCollection()->map($transform)->values()
            : $paginator->getCollection()->values();

        return response()->json([
            'data' => $items,
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'perPage' => $paginator->perPage(),
        ]);
    }
}
