<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TransactionResource;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

/**
 * @group Transactions
 *
 * Manage transactions.
 *
 * @authenticated
 */
class TransactionController extends Controller
{
    /**
     * List all transactions
     *
     * @queryParam page integer The page number. Example: 1
     * @queryParam per_page integer The number of transactions per page, from 1 to 100. Defaults to 20. Example: 20
     * @queryParam type string Filter by transaction type. Enum: income, expense Example: expense
     * @queryParam category_id integer Filter by an owned category ID. Example: 1
     * @queryParam tag_ids integer[] Filter by owned tag IDs. Returns transactions with any of the tags. Must contain at least one ID. Omit to include all transactions. Example: [1, 2]
     * @queryParam date_from string Include transactions on or after this date in YYYY-MM-DD format. Example: 2026-07-01
     * @queryParam date_to string Include transactions on or before this date in YYYY-MM-DD format. Example: 2026-07-31
     */
    public function index(Request $request): JsonResponse
    {
        $validated = validator($request->query(), [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'type' => ['sometimes', Rule::enum(TransactionType::class)],
            'category_id' => ['sometimes', 'integer', Rule::exists('categories', 'id')->where('user_id', $request->user()->getKey())],
            'tag_ids' => ['sometimes', 'bail', 'array', 'list', 'min:1', Rule::exists('tags', 'id')->where('user_id', $request->user()->getKey())],
            'tag_ids.*' => ['required', 'integer', 'distinct'],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ])->validate();
        $perPage = (int) ($validated['per_page'] ?? 20);
        $paginator = Transaction::query()
            ->where('user_id', $request->user()->getKey())
            ->with($this->relationships($request))
            ->when(isset($validated['type']), fn($query) => $query->ofType(TransactionType::from($validated['type'])))
            ->when(isset($validated['category_id']), fn($query) => $query->where('category_id', $validated['category_id']))
            ->when(isset($validated['tag_ids']), fn(Builder $query): Builder => $query->whereHas(
                'tags',
                fn(Builder $tags): Builder => $tags->whereIn('tags.id', $validated['tag_ids']),
            ))
            ->when(isset($validated['date_from']), fn($query) => $query->whereDate('transaction_date', '>=', $validated['date_from']))
            ->when(isset($validated['date_to']), fn($query) => $query->whereDate('transaction_date', '<=', $validated['date_to']))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate($perPage);
        $paginator->appends([...Arr::except($validated, ['page', 'per_page']), 'per_page' => $perPage]);

        return $this->collectionResponse($request, $paginator);
    }

    /**
     * Add a new transaction
     *
     * @bodyParam title string The transaction title. Can be null. Maximum 255 characters. Example: Lunch
     * @bodyParam type string required The transaction type. Enum: income, expense Example: expense
     * @bodyParam amount number required The non-negative amount with at most two decimal places. Example: 12.30
     * @bodyParam category_id integer The ID of an owned, active category. Can be null. Example: 1
     * @bodyParam tag_ids integer[] The IDs of owned tags. Defaults to an empty array. Example: [1, 2]
     * @bodyParam notes string Additional notes. Can be null. Example: Team lunch
     * @bodyParam transaction_date string required The transaction date in YYYY-MM-DD format. Example: 2026-07-11
     */
    #[ResponseFromApiResource(TransactionResource::class, Transaction::class, status: 201, with: ['category', 'tags'])]
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateWrite($request);
        $transaction = DB::transaction(function () use ($request, $validated): Transaction {
            $transaction = Transaction::create([
                ...Arr::except($validated, ['tag_ids']),
                'user_id' => $request->user()->getKey(),
            ]);
            $transaction->tags()->sync($validated['tag_ids'] ?? []);

            return $transaction;
        })->load($this->relationships($request));
        $location = route('api.v1.transactions.show', $transaction);

        return (new TransactionResource($transaction))->response()->setStatusCode(201)->header('Location', $location);
    }

    /**
     * Show a specified transaction
     */
    #[ResponseFromApiResource(TransactionResource::class, Transaction::class, with: ['category', 'tags'])]
    public function show(Request $request, string $transaction): TransactionResource
    {
        return new TransactionResource($this->find($request, $transaction));
    }

    /**
     * Update a specified transaction
     *
     * @bodyParam title string The transaction title. Can be null. Maximum 255 characters. Example: Lunch
     * @bodyParam type string The transaction type. Enum: income, expense Example: expense
     * @bodyParam amount number The non-negative amount with at most two decimal places. Example: 12.30
     * @bodyParam category_id integer The ID of an owned, active category. Can be null. Example: 1
     * @bodyParam tag_ids integer[] The IDs of owned tags. Replaces existing tags when provided. Use an empty array to remove all tags. Example: [1, 2]
     * @bodyParam notes string Additional notes. Can be null. Example: Team lunch
     * @bodyParam transaction_date string The transaction date in YYYY-MM-DD format. Example: 2026-07-11
     */
    #[ResponseFromApiResource(TransactionResource::class, Transaction::class, with: ['category', 'tags'])]
    public function update(Request $request, string $transaction): TransactionResource
    {
        $model = $this->find($request, $transaction);
        $validated = $this->validateWrite($request, true);
        DB::transaction(function () use ($model, $validated): void {
            $model->update(Arr::except($validated, ['tag_ids']));

            if (array_key_exists('tag_ids', $validated)) {
                $model->tags()->sync($validated['tag_ids']);
            }
        });

        return new TransactionResource($model->refresh()->load($this->relationships($request)));
    }

    /**
     * Delete a specified transaction
     */
    public function destroy(Request $request, string $transaction): Response
    {
        $this->find($request, $transaction)->delete();

        return response()->noContent();
    }

    private function find(Request $request, string $id): Transaction
    {
        return Transaction::where('user_id', $request->user()->getKey())->with($this->relationships($request))->findOrFail($id);
    }

    /**
     * @return array{0: string, tags: \Closure}
     */
    private function relationships(Request $request): array
    {
        return [
            'category',
            'tags' => fn(BelongsToMany $query): BelongsToMany => $query
                ->where('tags.user_id', $request->user()->getKey())
                ->orderBy('name')
                ->orderBy('tags.id'),
        ];
    }

    /**
     * @return array{title?: ?string, type?: string, amount?: numeric-string|int|float, category_id?: int|null, tag_ids?: list<int>, notes?: ?string, transaction_date?: string}
     */
    private function validateWrite(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'type' => [$required, Rule::enum(TransactionType::class)],
            'amount' => [$required, 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999999.99'],
            'category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('categories', 'id')->where(fn($query) => $query
                ->where('user_id', $request->user()->getKey())
                ->where('is_active', true)
                ->whereNull('deleted_at'))],
            'tag_ids' => ['sometimes', 'bail', 'array', 'list', Rule::exists('tags', 'id')->where('user_id', $request->user()->getKey())],
            'tag_ids.*' => ['required', 'integer', 'distinct'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'transaction_date' => [$required, 'date_format:Y-m-d'],
        ]);
    }

    private function collectionResponse(Request $request, LengthAwarePaginator $paginator): JsonResponse
    {
        $link = fn(int $page): array => ['href' => $paginator->url($page), 'method' => 'GET'];

        return response()->json([
            'data' => collect($paginator->items())->map(fn(Transaction $transaction): array => (new TransactionResource($transaction))->toArray($request))->all(),
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
                'create' => ['href' => route('api.v1.transactions.store'), 'method' => 'POST'],
            ],
        ]);
    }
}
