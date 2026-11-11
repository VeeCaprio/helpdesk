<?php

namespace App\Filament\Admin\Pages;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffPerformance extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Staff Performance';

    protected static ?string $title = 'Staff Performance';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'reports/staff-performance';

    protected static string $view = 'filament.admin.pages.staff-performance';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->whereHas('roles', fn (Builder $query): Builder => $query->where('name', User::ROLE_STAFF))
                    ->withCount([
                        'assignedTickets as assigned_total',
                        'assignedTickets as active_total' => fn (Builder $query): Builder => $query->whereIn('status', [
                            TicketStatus::Open->value,
                            TicketStatus::InProgress->value,
                            TicketStatus::Pending->value,
                        ]),
                        'assignedTickets as completed_total' => fn (Builder $query): Builder => $query->whereIn('status', [
                            TicketStatus::Resolved->value,
                            TicketStatus::Closed->value,
                        ]),
                    ])
                    ->with([
                        'assignedTickets' => fn (HasMany $query): HasMany => $query
                            ->whereIn('status', [
                                TicketStatus::Resolved->value,
                                TicketStatus::Closed->value,
                            ])
                            ->whereNotNull('resolved_at')
                            ->select(['id', 'assigned_to', 'created_at', 'resolved_at']),
                    ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Staff')
                    ->searchable()
                    ->sortable()
                    ->description(fn (User $record): string => $record->email),
                Tables\Columns\TextColumn::make('assigned_total')
                    ->label('Assigned')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('active_total')
                    ->label('Active')
                    ->badge()
                    ->color('warning')
                    ->sortable()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('completed_total')
                    ->label('Completed')
                    ->badge()
                    ->color('success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('resolution_rate')
                    ->label('Resolution Rate')
                    ->state(fn (User $record): string => $this->resolutionRate($record))
                    ->badge()
                    ->color(fn (User $record): string => $this->resolutionRateColor($record)),
                Tables\Columns\TextColumn::make('average_resolution')
                    ->label('Avg. Resolution')
                    ->state(fn (User $record): string => $this->averageResolution($record))
                    ->tooltip('Average time from creation to resolution')
                    ->visibleFrom('md'),
            ])
            ->defaultSort('name')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateHeading('No staff members yet')
            ->emptyStateDescription('Assign the staff role to a user to include them in this report.');
    }

    private function resolutionRate(User $staff): string
    {
        if ($staff->assigned_total === 0) {
            return '—';
        }

        return round(($staff->completed_total / $staff->assigned_total) * 100) . '%';
    }

    private function resolutionRateColor(User $staff): string
    {
        if ($staff->assigned_total === 0) {
            return 'gray';
        }

        $rate = ($staff->completed_total / $staff->assigned_total) * 100;

        return match (true) {
            $rate >= 80 => 'success',
            $rate >= 50 => 'warning',
            default => 'danger',
        };
    }

    private function averageResolution(User $staff): string
    {
        $tickets = $staff->assignedTickets;

        if ($tickets->isEmpty()) {
            return '—';
        }

        $seconds = (int) round($tickets->avg(
            fn (Ticket $ticket): int => (int) $ticket->created_at->diffInSeconds($ticket->resolved_at)
        ));

        return $this->formatDuration($seconds);
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = intdiv($seconds, 60);

        if ($minutes < 60) {
            return "{$minutes}m";
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours < 24) {
            return $remainingMinutes > 0
                ? "{$hours}h {$remainingMinutes}m"
                : "{$hours}h";
        }

        $days = intdiv($hours, 24);
        $remainingHours = $hours % 24;

        return $remainingHours > 0
            ? "{$days}d {$remainingHours}h"
            : "{$days}d";
    }
}
