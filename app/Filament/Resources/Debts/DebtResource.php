<?php

namespace App\Filament\Resources\Debts;

use App\Filament\Resources\Debts\Pages\EditDebt;
use App\Filament\Resources\Debts\Pages\ListDebts;
use App\Filament\Resources\Debts\RelationManagers\TransactionsRelationManager;
use App\Filament\Resources\Debts\Schemas\DebtForm;
use App\Filament\Resources\Debts\Tables\DebtsTable;
use App\Models\Debt;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class DebtResource extends Resource
{
    protected static ?string $model = Debt::class;

    protected static string|UnitEnum|null $navigationGroup = 'Management';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Scale;

    protected static ?string $recordTitleAttribute = 'other_person';

    public static function form(Schema $schema): Schema
    {
        return DebtForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DebtsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDebts::route('/'),
            'edit' => EditDebt::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id());
    }
}
