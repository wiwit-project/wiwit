<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'type',
        'amount',
        'category_id',
        'notes',
        'transaction_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    /**
     * Dynamically query the transaction type
     */
    #[Scope]
    protected function ofType(Builder $query, TransactionType $type): void
    {
        $query->where('type', $type->value);
    }

    /**
     * Scope a query to only include expenses.
     */
    #[Scope]
    protected function expenses(Builder $query): void
    {
        $query->where('type', TransactionType::Expense);
    }

    /**
     * Scope a query to only include incomes.
     */
    #[Scope]
    protected function incomes(Builder $query): void
    {
        $query->where('type', TransactionType::Income);
    }
}
