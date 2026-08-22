<?php

namespace App\Filament\Imports;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;

class TransactionImporter extends Importer
{
    protected static ?string $model = Transaction::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('amount')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'numeric']),
            ImportColumn::make('category')
                ->guess(['category'])
                ->relationship(resolveUsing: function (string $state): ?Category {
                    // Find or create category
                    $category = Category::firstOrCreate(
                        [
                            'user_id' => Auth::id(),
                            'name' => $state,
                        ],
                        [
                            'is_active' => true,
                        ]
                    );

                    return $category;
                }),
            ImportColumn::make('title')
                ->guess(['title'])
                ->rules(['string', 'max:255', 'nullable']),
            ImportColumn::make('notes')
                ->guess(['description'])
                ->rules(['string', 'nullable']),
            ImportColumn::make('transaction_date')
                ->guess(['date'])
                ->rules(['required', 'date']),
        ];
    }

    public function resolveRecord(): Transaction
    {
        return new Transaction([
            'user_id' => Auth::id(),
        ]);
    }

    protected function beforeFill(): void
    {
        $amount = (float) $this->data['amount'];

        $this->record->type = $amount > 0 ? TransactionType::Income : TransactionType::Expense;
        $this->data['amount'] = number_format(abs($amount), 2, '.', '');
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your transaction import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
