<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Filament\Admin\Resources\TicketResource;
use App\Models\Ticket;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentTicketsTable extends BaseWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected function getTableHeading(): ?string
    {
        return 'Recent Tickets';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Ticket::query()
                    ->visibleTo(auth()->user())
                    ->with(['reporter', 'assignee'])
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('ticket_number')
                    ->label('Ticket'),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->limit(45)
                    ->tooltip(fn (Ticket $record): string => $record->subject),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (TicketStatus $state): string => $state->label())
                    ->color(fn (TicketStatus $state): string => $state->color()),
                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->formatStateUsing(fn (TicketPriority $state): string => $state->label())
                    ->color(fn (TicketPriority $state): string => $state->color()),
                Tables\Columns\TextColumn::make('reporter.name')
                    ->label('Reporter'),
                Tables\Columns\TextColumn::make('assignee.name')
                    ->label('Assigned To')
                    ->placeholder('Unassigned'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->since(),
            ])
            ->recordUrl(
                fn (Ticket $record): string => TicketResource::getUrl('view', ['record' => $record])
            )
            ->paginated(false)
            ->emptyStateIcon('heroicon-o-ticket')
            ->emptyStateHeading('No tickets yet')
            ->emptyStateDescription('Tickets that you can access will appear here.');
    }
}
