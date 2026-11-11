<?php

namespace App\Filament\Admin\Resources;

use App\Models\TicketCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TicketCategoryResource extends Resource
{
    protected static ?string $model = TicketCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Categories';

    protected static ?string $modelLabel = 'Category';

    protected static ?string $pluralModelLabel = 'Categories';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Category Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Category Name')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true)
                            ->autocomplete(false)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Jelaskan jenis permasalahan pada kategori ini…')
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Kategori nonaktif tidak bisa dipilih pada tiket baru.')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('slug')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(45)
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('tickets_count')
                    ->counts('tickets')
                    ->label('Tickets')
                    ->badge()
                    ->color('info'),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active')
                    ->afterStateUpdated(function (TicketCategory $record, bool $state): void {
                        \Filament\Notifications\Notification::make()
                            ->title($state ? 'Kategori diaktifkan' : 'Kategori dinonaktifkan')
                            ->body($record->name)
                            ->success()
                            ->send();
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->disabled(fn (TicketCategory $record): bool => $record->tickets()->exists())
                    ->tooltip(fn (TicketCategory $record): ?string => $record->tickets()->exists()
                        ? 'Kategori masih dipakai oleh tiket.'
                        : null),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Admin\Resources\TicketCategoryResource\Pages\ListTicketCategories::route('/'),
            'create' => \App\Filament\Admin\Resources\TicketCategoryResource\Pages\CreateTicketCategory::route('/create'),
            'edit' => \App\Filament\Admin\Resources\TicketCategoryResource\Pages\EditTicketCategory::route('/{record}/edit'),
        ];
    }

    /**
     * Khusus form tiket: hanya kategori aktif.
     *
     * @return Builder<TicketCategory>
     */
    public static function getActiveCategoriesQuery(): Builder
    {
        return TicketCategory::query()->where('is_active', true)->orderBy('name');
    }
}
