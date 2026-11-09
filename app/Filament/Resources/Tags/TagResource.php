<?php

namespace App\Filament\Resources\Tags;

use App\Filament\Resources\Tags\Pages\ManageTags;
use App\Models\Tag;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static string|UnitEnum|null $navigationGroup = 'Management';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHashtag;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Hashtag;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->maxLength(255)
                    ->scopedUnique(
                        ignoreRecord: true,
                        modifyQueryUsing: fn (Builder $query): Builder => $query->where('user_id', auth()->id()),
                    )
                    ->required(),
                ColorPicker::make('color')
                    ->hex()
                    ->nullable()
                    ->regex('/^#[a-fA-F0-9]{6}$/')
                    ->placeholder('#3b82f6'),
                TextInput::make('description')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                ColorColumn::make('color')
                    ->placeholder('—')
                    ->tooltip(fn (Tag $record): ?string => $record->color),
                TextColumn::make('description')
                    ->wrap()
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTags::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id());
    }
}
