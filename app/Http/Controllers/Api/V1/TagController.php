<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TagResource;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

/**
 * @group Tags
 *
 * Manage tags
 *
 * @authenticated
 */
class TagController extends Controller
{
    /**
     * List all tags
     *
     * @queryParam page integer The page number. Example: 1
     * @queryParam per_page integer The number of tags per page, from 1 to 100. Defaults to 20. Example: 20
     */
    public function index(Request $request): JsonResponse
    {
        $validated = validator($request->query(), [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ])->validate();
        $perPage = (int) ($validated['per_page'] ?? 20);
        $paginator = Tag::query()
            ->where('user_id', $request->user()->getKey())
            ->withCount('transactions')
            ->orderBy('id')
            ->paginate($perPage);
        $paginator->appends([...Arr::except($validated, ['page', 'per_page']), 'per_page' => $perPage]);

        return $this->collectionResponse($request, $paginator);
    }

    /**
     * Add new tag
     *
     * @bodyParam name string required The tag name. Maximum 255 characters. Example: Food
     * @bodyParam color string Optional six-digit hex color. Use null to clear it. Example: #3b82f6
     * @bodyParam description string Optional tag description. Maximum 255 characters. Example: Everyday expenses
     */
    #[ResponseFromApiResource(TagResource::class, Tag::class, status: 201)]
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateWrite($request);
        $this->ensureNameIsAvailable($request, $validated['name']);

        try {
            $tag = Tag::create([
                ...$validated,
                'user_id' => $request->user()->getKey(),
            ]);
        } catch (UniqueConstraintViolationException) {
            abort(409);
        }

        $location = route('api.v1.tags.show', $tag);

        return (new TagResource($tag->loadCount('transactions')))->response()->setStatusCode(201)->header('Location', $location);
    }

    /**
     * Show a specified Tag
     */
    #[ResponseFromApiResource(TagResource::class, Tag::class)]
    public function show(Request $request, string $tag): TagResource
    {
        return new TagResource($this->find($request, $tag));
    }

    /**
     * Update a specified Tag
     *
     * @bodyParam name string The tag name. Maximum 255 characters. Example: Food & Groceries
     * @bodyParam color string Optional six-digit hex color. Use null to clear it. Example: #3b82f6
     * @bodyParam description string Optional tag description. Maximum 255 characters. Use null to clear it. Example: Everyday expenses
     */
    #[ResponseFromApiResource(TagResource::class, Tag::class)]
    public function update(Request $request, string $tag): TagResource
    {
        $model = $this->find($request, $tag);
        $validated = $this->validateWrite($request, true);

        if (isset($validated['name'])) {
            $this->ensureNameIsAvailable($request, $validated['name'], $model);
        }

        try {
            $model->update($validated);
        } catch (UniqueConstraintViolationException) {
            abort(409);
        }

        return new TagResource($model->refresh()->loadCount('transactions'));
    }

    /**
     * Delete a specified Tag
     */
    public function destroy(Request $request, string $tag): Response
    {
        $this->find($request, $tag)->delete();

        return response()->noContent();
    }

    private function find(Request $request, string $id): Tag
    {
        return Tag::where('user_id', $request->user()->getKey())->withCount('transactions')->findOrFail($id);
    }

    /**
     * @return array{name?: string, description?: ?string, color?: ?string}
     */
    private function validateWrite(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'color' => ['sometimes', 'nullable', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
        ]);
    }

    private function ensureNameIsAvailable(Request $request, string $name, ?Tag $except = null): void
    {
        $exists = Tag::where('user_id', $request->user()->getKey())
            ->where('name', $name)
            ->when($except, fn (Builder $query): Builder => $query->whereKeyNot($except->getKey()))
            ->exists();

        if ($exists) {
            abort(409);
        }
    }

    private function collectionResponse(Request $request, LengthAwarePaginator $paginator): JsonResponse
    {
        $link = fn (int $page): array => ['href' => $paginator->url($page), 'method' => 'GET'];

        return response()->json([
            'data' => collect($paginator->items())->map(fn (Tag $tag): array => (new TagResource($tag))->toArray($request))->all(),
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
                'create' => ['href' => route('api.v1.tags.store'), 'method' => 'POST'],
            ],
        ]);
    }
}
