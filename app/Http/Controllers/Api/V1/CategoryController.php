<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

/**
 * @group Categories
 *
 * Manage categories
 *
 * @authenticated
 */
class CategoryController extends Controller
{
    /**
     * List all categories
     *
     * @queryParam page integer The page number. Example: 1
     * @queryParam per_page integer The number of categories per page, from 1 to 100. Defaults to 20. Example: 20
     * @queryParam show_inactive boolean Include inactive categories. Example: true
     * @queryParam sort string Order the results. Accepts "name" (default), "most_used". Example: most_used
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $validated = validator($request->query(), [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'show_inactive' => ['sometimes', 'in:true,false,1,0'],
            'sort' => ['sometimes', 'in:name,most_used'],
        ])->validate();
        $perPage = (int) ($validated['per_page'] ?? 20);
        $paginator = Category::query()
            ->where('user_id', $request->user()->getKey())
            ->when(! $request->boolean('show_inactive'), fn ($query) => $query->where('is_active', true))
            ->withCount('transactions')
            ->when(
                ($validated['sort'] ?? 'name') === 'most_used',
                fn ($query) => $query->orderByDesc('transactions_count'),
            )
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage);
        $paginator->appends([...Arr::except($validated, ['page', 'per_page']), 'per_page' => $perPage]);

        return $this->collectionResponse($request, $paginator);
    }

    /**
     * Add new category
     *
     * @bodyParam name string required The category name. Maximum 255 characters. Example: Food
     * @bodyParam is_active boolean Whether the category is active. Defaults to true. Example: true
     */
    #[ResponseFromApiResource(CategoryResource::class, Category::class, status: 201)]
    public function store(Request $request)
    {
        $validated = $this->validateWrite($request);
        $this->ensureNameIsAvailable($request, $validated['name']);

        $category = Category::create([
            'is_active' => true,
            ...$validated,
            'user_id' => $request->user()->getKey(),
        ]);

        $location = route('api.v1.categories.show', $category);

        return (new CategoryResource($category->loadCount('transactions')))->response()->setStatusCode(201)->header('Location', $location);
    }

    /**
     * Show a specified Category
     */
    #[ResponseFromApiResource(CategoryResource::class, Category::class)]
    public function show(Request $request, string $category)
    {
        return new CategoryResource($this->find($request, $category));
    }

    /**
     * Update a specified Category
     *
     * @bodyParam name string The category name. Maximum 255 characters. Example: Food & Groceries
     * @bodyParam is_active boolean Whether the category is active. Example: false
     */
    #[ResponseFromApiResource(CategoryResource::class, Category::class)]
    public function update(Request $request, string $category)
    {
        $model = $this->find($request, $category);
        $validated = $this->validateWrite($request, true);

        if (isset($validated['name'])) {
            $this->ensureNameIsAvailable($request, $validated['name'], $model);
        }

        $model->update($validated);

        return new CategoryResource($model->refresh()->loadCount('transactions'));
    }

    /**
     * Delete a specified Category
     */
    public function destroy(Request $request, string $category)
    {
        $this->find($request, $category)->delete();

        return response()->noContent();
    }

    private function find(Request $request, string $id): Category
    {
        return Category::where('user_id', $request->user()->getKey())->withCount('transactions')->findOrFail($id);
    }

    private function validateWrite(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function ensureNameIsAvailable(Request $request, string $name, ?Category $except = null): void
    {
        $exists = Category::where('user_id', $request->user()->getKey())
            ->where('name', $name)
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists();

        if ($exists) {
            abort(409);
        }
    }

    private function collectionResponse(Request $request, LengthAwarePaginator $paginator)
    {
        $link = fn (int $page): array => ['href' => $paginator->url($page), 'method' => 'GET'];

        return response()->json([
            'data' => collect($paginator->items())->map(fn (Category $category): array => (new CategoryResource($category))->toArray($request))->all(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'links' => [
                'self' => $link($paginator->currentPage()),
                'first' => $link(1),
                'prev' => $paginator->currentPage() > 1 ? $link($paginator->currentPage() - 1) : null,
                'next' => $paginator->hasMorePages() ? $link($paginator->currentPage() + 1) : null,
                'last' => $link($paginator->lastPage()),
                'create' => ['href' => route('api.v1.categories.store'), 'method' => 'POST'],
            ],
        ]);
    }
}
