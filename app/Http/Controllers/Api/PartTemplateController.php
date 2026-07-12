<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithPagination;
use App\Http\Controllers\Controller;
use App\Models\CarMake;
use App\Models\CarModel;
use App\Models\PartTemplate;
use App\Services\Inventory\PartTranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PartTemplateController extends Controller
{
    use RespondsWithPagination;

    public function __construct(
        private readonly PartTranslationService $translations
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = (int) $request->query('per_page', 10);

        $query = PartTemplate::query()
            ->with(['make', 'model'])
            ->orderBy('id', 'desc');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('name_ru', 'like', "%{$search}%")
                    ->orWhere('name_hy', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('path', 'like', "%{$search}%")
                    ->orWhereHas('make', fn ($makeQuery) => $makeQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('model', fn ($modelQuery) => $modelQuery->where('name', 'like', "%{$search}%"));
            });
        }

        return $this->paginated($query->paginate($perPage), fn (PartTemplate $template) => $this->mapTemplate($template));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateTemplate($request);
        $template = PartTemplate::create($this->preparePayload($validated));

        return response()->json($this->mapTemplate($template), 201);
    }

    public function update(Request $request, PartTemplate $partTemplate): JsonResponse
    {
        $validated = $this->validateTemplate($request, $partTemplate->id);
        $partTemplate->update($this->preparePayload($validated));

        return response()->json($this->mapTemplate($partTemplate->refresh()));
    }

    public function destroy(PartTemplate $partTemplate): JsonResponse
    {
        $partTemplate->delete();

        return response()->json(['message' => 'Part template deleted']);
    }

    private function validateTemplate(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'car_make_id' => ['required', 'integer', 'exists:car_makes,id'],
            'car_model_id' => ['required', 'integer', 'exists:car_models,id'],
            'start_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'end_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'name_ru' => ['nullable', 'string', 'max:120'],
            'name_hy' => ['nullable', 'string', 'max:120'],
            'sku' => ['required', 'string', 'max:60', Rule::unique('part_templates', 'sku')->ignore($ignoreId)],
            'category' => ['required', 'string', 'max:120'],
            'path' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:2048'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function preparePayload(array $validated): array
    {
        $translations = $this->translations->translate($validated['name']);
        $nameRu = isset($validated['name_ru']) && trim((string) $validated['name_ru']) !== ''
            ? trim((string) $validated['name_ru'])
            : $translations['ru'];
        $nameHy = isset($validated['name_hy']) && trim((string) $validated['name_hy']) !== ''
            ? trim((string) $validated['name_hy'])
            : $translations['hy'];

        return [
            'car_make_id' => $validated['car_make_id'],
            'car_model_id' => $validated['car_model_id'],
            'start_year' => $validated['start_year'] ?? null,
            'end_year' => $validated['end_year'] ?? $validated['start_year'] ?? null,
            'name' => $validated['name'],
            'name_ru' => $nameRu,
            'name_hy' => $nameHy,
            'sku' => $validated['sku'],
            'category' => $validated['category'],
            'path' => $validated['path'],
            'url' => $validated['url'],
            'image_url' => $validated['image_url'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ];
    }

    private function mapTemplate(PartTemplate $template): array
    {
        return [
            'id' => $template->id,
            'car_make_id' => $template->car_make_id,
            'car_model_id' => $template->car_model_id,
            'make' => $template->make?->name ?? '',
            'model' => $template->model?->name ?? '',
            'start_year' => $template->start_year,
            'end_year' => $template->end_year,
            'name' => $template->name,
            'name_ru' => $template->name_ru,
            'name_hy' => $template->name_hy,
            'sku' => $template->sku,
            'category' => $template->category,
            'path' => $template->path,
            'url' => $template->url,
            'image_url' => $template->image_url,
            'sort_order' => $template->sort_order,
            'fitment' => trim(sprintf(
                '%s %s %s-%s',
                $template->make?->name ?? '',
                $template->model?->name ?? '',
                $template->start_year ?? '',
                $template->end_year ?? ''
            )),
        ];
    }
}
