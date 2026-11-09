<?php

namespace App\Filament\Resources\Tags;

use App\Filament\Resources\Tags\Pages\ManageTags;
use App\Models\Tag;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
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
                    ->live(debounce: 300)
                    ->scopedUnique(
                        ignoreRecord: true,
                        modifyQueryUsing: fn(Builder $query): Builder => $query->where('user_id', auth()->id()),
                    )
                    ->required(),
                ColorPicker::make('color')
                    ->hex()
                    ->live(debounce: 250)
                    ->default(fn(): string => static::generateRandomColor())
                    ->nullable()
                    ->regex('/^#[a-fA-F0-9]{6}$/')
                    ->placeholder('#3b82f6')
                    ->suffixAction(
                        Action::make('randomizeColor')
                            ->label('Choose a random color')
                            ->icon(Heroicon::ArrowPath)
                            ->tooltip('Choose a random color')
                            ->action(function (Get $schemaGet, Set $schemaSet): void {
                                $schemaSet('color', static::generateRandomColor($schemaGet('color')));
                            }),
                    ),
                TextEntry::make('preview')
                    ->label('Preview')
                    ->state(fn(Get $get): string => filled($get('name')) ? $get('name') : 'Tag name')
                    ->badge()
                    ->color('gray')
                    ->extraAttributes(fn(Get $get): array => static::getNameBadgeAttributes($get('color')))
                    ->dehydrated(false)
                    ->columnSpanFull(),
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
                    ->badge()
                    ->color('gray')
                    ->extraAttributes(fn(Tag $record): array => static::getNameBadgeAttributes($record->color))
                    ->searchable()
                    ->sortable(),
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

    protected static function generateRandomColor(?string $currentColor = null): string
    {
        do {
            $color = sprintf('#%06x', random_int(0, 0xFFFFFF));
        } while ($color === strtolower($currentColor ?? ''));

        return $color;
    }

    /**
     * @return array{class: string, style: string}
     */
    protected static function getNameBadgeAttributes(?string $color): array
    {
        $backgroundColor = preg_match('/^#[a-fA-F0-9]{6}$/', $color ?? '') === 1 ? $color : '#e5e7eb';
        $linearChannels = array_map(function (int $channel): float {
            $normalizedChannel = $channel / 255;
            return $normalizedChannel <= 0.04045 ? $normalizedChannel / 12.92 : (($normalizedChannel + 0.055) / 1.055) ** 2.4;
        }, sscanf($backgroundColor, '#%02x%02x%02x'));

        $luminance = 0.2126 * $linearChannels[0] + 0.7152 * $linearChannels[1] + 0.0722 * $linearChannels[2];
        $blackContrast = ($luminance + 0.05) / 0.05;
        $whiteContrast = 1.05 / ($luminance + 0.05);
        $textColor = $blackContrast >= $whiteContrast ? '#000000' : '#ffffff';

        return [
            'class' => 'tag-name-badge',
            'style' => "--tag-background-color: {$backgroundColor}; --tag-text-color: {$textColor};",
        ];
    }
}
