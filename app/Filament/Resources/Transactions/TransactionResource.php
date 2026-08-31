<?php

namespace App\Filament\Resources\Transactions;

use App\Enums\TransactionType;
use App\Filament\Imports\TransactionImporter;
use App\Filament\Resources\Transactions\Pages\ManageTransactions;
use App\Models\Category;
use App\Models\Transaction;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\ImportAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|UnitEnum|null $navigationGroup = 'Management';

    protected static ?int $navigationSort = 0;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Banknotes;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->maxLength(255),
                Select::make('type')
                    ->options(TransactionType::class)
                    ->default(TransactionType::Expense)
                    ->required(),
                TextInput::make('amount')
                    ->required()
                    ->rules(['numeric', 'min:0'])
                    // To format the number to 2 decimal places when focus changes
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        if ($state === null || $state === '' || ! is_numeric($state)) {
                            return;
                        }

                        $set('amount', number_format((float) $state, 2, '.', ''));
                    }),
                Select::make('category_id')
                    ->relationship(
                        'category',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query
                            ->where('user_id', auth()->id())
                            ->where('is_active', true),
                    )
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->maxLength(255)
                            ->scopedUnique(
                                Category::class,
                                ignoreRecord: true,
                                modifyQueryUsing: fn (Builder $query): Builder => $query->where('user_id', auth()->id()),
                            )
                            ->required(),
                    ])
                    ->createOptionUsing(fn (array $data): int => Category::create([
                        ...$data,
                        'user_id' => auth()->id(),
                        'is_active' => true,
                    ])->getKey()),
                DatePicker::make('transaction_date')
                    ->required()
                    ->default(today()),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('title')
                    ->wrap()
                    ->limit(50)
                    ->searchable()
                    ->weight(fn (Transaction $record): string => $record->transaction_date->isToday() ? 'bold' : 'normal'),
                TextColumn::make('amount')
                    ->formatStateUsing(fn ($state, Transaction $record): string => ($record->type === TransactionType::Income ? '+' : '-').number_format((float) $state, 2, '.', ''))
                    ->color(fn (Transaction $record): string => $record->type === TransactionType::Income ? 'success' : 'danger')
                    ->sortable()
                    ->weight(fn (Transaction $record): string => $record->transaction_date->isToday() ? 'bold' : 'normal'),
                TextColumn::make('transaction_date')
                    ->date()
                    ->sortable()
                    ->weight(fn (Transaction $record): string => $record->transaction_date->isToday() ? 'bold' : 'normal'),
                TextColumn::make('category.name')
                    ->sortable()
                    ->searchable()
                    ->weight(fn (Transaction $record): string => $record->transaction_date->isToday() ? 'bold' : 'normal'),
                TextColumn::make('notes')
                    ->wrap()
                    ->limit(20)
                    ->weight(fn (Transaction $record): string => $record->transaction_date->isToday() ? 'bold' : 'normal')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
                ImportAction::make()
                    ->importer(TransactionImporter::class),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTransactions::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id());
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
