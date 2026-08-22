<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The Transaction types.
 */
enum TransactionType: string implements HasLabel
{
    case Income = 'income';

    case Expense = 'expense';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Income => 'Income',
            self::Expense => 'Expense',
        };
    }
}
