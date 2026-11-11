<?php

namespace App\Filament\Admin\Resources\TicketResource\RelationManagers;

use App\Models\TicketHistory;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Riwayat tiket — read-only, kronologis, tidak pernah diubah/dihapus.
 */
class HistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'histories';

    protected static ?string $title = 'History';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->tooltip(fn (TicketHistory $record): string => $record->created_at->format('d M Y H:i:s'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('By')
                    ->placeholder('Sistem')
                    ->badge()
                    ->color('gray')
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('activity')
                    ->label('Activity')
                    ->badge()
                    ->color(fn (TicketHistory $record): string => match ($record->activity) {
                        \App\Models\Ticket::ACTIVITY_RESOLVED => 'success',
                        \App\Models\Ticket::ACTIVITY_CREATED => 'info',
                        default => 'warning',
                    })
                    ->getStateUsing(fn (TicketHistory $record): string => $record->activityLabel()),
                Tables\Columns\TextColumn::make('old_value')
                    ->label('From')
                    ->placeholder('-')
                    ->limit(40)
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('new_value')
                    ->label('To')
                    ->placeholder('-')
                    ->limit(40)
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('description')
                    ->label('Details')
                    ->wrap()
                    ->limit(120)
                    ->placeholder('-')
                    ->visibleFrom('md'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->actions([])
            ->bulkActions([]);
    }

    /**
     * Riwayat tidak boleh diubah maupun dihapus.
     */
    protected function canCreate(): bool
    {
        return false;
    }

    protected function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    protected function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    protected function canDeleteAny(): bool
    {
        return false;
    }
}
