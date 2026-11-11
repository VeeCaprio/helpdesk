<?php

namespace App\Filament\Admin\Pages;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Filament\Admin\Resources\TicketResource;
use App\Models\Ticket;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TicketReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Ticket Report';

    protected static ?string $title = 'Ticket Report';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'reports/tickets';

    protected static string $view = 'filament.admin.pages.ticket-report';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Ticket::query()
                    ->visibleTo(auth()->user())
                    ->with(['category', 'reporter', 'assignee'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->searchable(['subject', 'ticket_number'])
                    ->sortable()
                    ->wrap()
                    ->limit(60)
                    ->tooltip(fn (Ticket $record): string => $record->subject)
                    ->description(fn (Ticket $record): string => $record->ticket_number . ' · ' . ($record->category?->name ?? '-')),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (TicketStatus $state): string => $state->label())
                    ->color(fn (TicketStatus $state): string => $state->color())
                    ->sortable(),
                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->formatStateUsing(fn (TicketPriority $state): string => $state->label())
                    ->color(fn (TicketPriority $state): string => $state->color())
                    ->sortable()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('reporter.name')
                    ->label('Reporter')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('assignee.name')
                    ->label('Assignee')
                    ->placeholder('Unassigned')
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('resolved_at')
                    ->label('Resolved')
                    ->dateTime('d M Y H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('created_at')
                    ->label('Date Range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From')
                            ->native(false),
                        Forms\Components\DatePicker::make('to')
                            ->label('To')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, string $from): Builder => $query->whereDate('created_at', '>=', $from)
                            )
                            ->when(
                                $data['to'] ?? null,
                                fn (Builder $query, string $to): Builder => $query->whereDate('created_at', '<=', $to)
                            );
                    }),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(TicketStatus::options())
                    ->multiple(),
                Tables\Filters\SelectFilter::make('priority')
                    ->label('Priority')
                    ->options(TicketPriority::options())
                    ->multiple(),
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),
                Tables\Filters\SelectFilter::make('assigned_to')
                    ->label('Assignee')
                    ->relationship('assignee', 'name'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->recordUrl(
                fn (Ticket $record): string => TicketResource::getUrl('view', ['record' => $record])
            )
            ->emptyStateIcon('heroicon-o-document-chart-bar')
            ->emptyStateHeading('No tickets match this report')
            ->emptyStateDescription('Adjust the date range or status filter to see more tickets.');
    }

    /**
     * Ringkasan mengikuti filter tabel yang sedang aktif.
     *
     * @return list<array{label: string, value: int, icon: string, description: string}>
     */
    public function summary(): array
    {
        $query = $this->getFilteredTableQuery();

        $total = (clone $query)->count();
        $open = (clone $query)->where('status', TicketStatus::Open->value)->count();
        $inProgress = (clone $query)->where('status', TicketStatus::InProgress->value)->count();
        $completed = (clone $query)
            ->whereIn('status', [TicketStatus::Resolved->value, TicketStatus::Closed->value])
            ->count();

        return [
            [
                'label' => 'Tickets',
                'value' => $total,
                'icon' => 'heroicon-o-ticket',
                'description' => 'Matching the current filters',
            ],
            [
                'label' => 'Open',
                'value' => $open,
                'icon' => 'heroicon-o-inbox',
                'description' => 'Waiting to be handled',
            ],
            [
                'label' => 'In Progress',
                'value' => $inProgress,
                'icon' => 'heroicon-o-arrow-path',
                'description' => 'Currently being worked on',
            ],
            [
                'label' => 'Completed',
                'value' => $completed,
                'icon' => 'heroicon-o-check-circle',
                'description' => 'Resolved or closed',
            ],
        ];
    }
}
