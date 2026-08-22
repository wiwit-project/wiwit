<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
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
     * @queryParam date_from string Include transactions on or after this date in YYYY-MM-DD format. Example: 2026-07-01
     * @queryParam date_to string Include transactions on or before this date in YYYY-MM-DD format. Example: 2026-07-31
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $validated = validator($request->query(), [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'type' => ['sometimes', Rule::enum(TransactionType::class)],
            'category_id' => ['sometimes', 'integer', Rule::exists('categories', 'id')->where('user_id', $request->user()->getKey())],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ])->validate();
        $perPage = (int) ($validated['per_page'] ?? 20);
        $paginator = Transaction::query()
            ->where('user_id', $request->user()->getKey())
            ->with('category')
            ->when(isset($validated['type']), fn ($query) => $query->where('type', $validated['type']))
            ->when(isset($validated['category_id']), fn ($query) => $query->where('category_id', $validated['category_id']))
            ->when(isset($validated['date_from']), fn ($query) => $query->whereDate('transaction_date', '>=', $validated['date_from']))
            ->when(isset($validated['date_to']), fn ($query) => $query->whereDate('transaction_date', '<=', $validated['date_to']))
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
     * @bodyParam notes string Additional notes. Can be null. Example: Team lunch
     * @bodyParam transaction_date string required The transaction date in YYYY-MM-DD format. Example: 2026-07-11
     */
    #[ResponseFromApiResource(TransactionResource::class, Transaction::class, status: 201, with: ['category'])]
    public function store(Request $request)
    {
        $transaction = Transaction::create([
            ...$this->validateWrite($request),
            'user_id' => $request->user()->getKey(),
        ])->load('category');
        $location = route('api.v1.transactions.show', $transaction);

        return (new TransactionResource($transaction))->response()->setStatusCode(201)->header('Location', $location);
    }

    /**
     * Show a specified transaction
     */
    #[ResponseFromApiResource(TransactionResource::class, Transaction::class, with: ['category'])]
    public function show(Request $request, string $transaction)
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
     * @bodyParam notes string Additional notes. Can be null. Example: Team lunch
     * @bodyParam transaction_date string The transaction date in YYYY-MM-DD format. Example: 2026-07-11
     */
    #[ResponseFromApiResource(TransactionResource::class, Transaction::class, with: ['category'])]
    public function update(Request $request, string $transaction)
    {
        $model = $this->find($request, $transaction);
        $model->update($this->validateWrite($request, true));

        return new TransactionResource($model->refresh()->load('category'));
    }

    /**
     * Delete a specified transaction
     */
    public function destroy(Request $request, string $transaction)
    {
        $this->find($request, $transaction)->delete();

        return response()->noContent();
    }

    private function find(Request $request, string $id): Transaction
    {
        return Transaction::where('user_id', $request->user()->getKey())->with('category')->findOrFail($id);
    }

    private function validateWrite(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'type' => [$required, Rule::enum(TransactionType::class)],
            'amount' => [$required, 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999999.99'],
            'category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('categories', 'id')->where(fn ($query) => $query
                ->where('user_id', $request->user()->getKey())
                ->where('is_active', true)
                ->whereNull('deleted_at'))],
            'notes' => ['sometimes', 'nullable', 'string'],
            'transaction_date' => [$required, 'date_format:Y-m-d'],
        ]);
    }

    private function collectionResponse(Request $request, LengthAwarePaginator $paginator)
    {
        $link = fn (int $page): array => ['href' => $paginator->url($page), 'method' => 'GET'];

        return response()->json([
            'data' => collect($paginator->items())->map(fn (Transaction $transaction): array => (new TransactionResource($transaction))->toArray($request))->all(),
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
