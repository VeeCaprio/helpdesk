<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\TicketStatus;
use App\Filament\Admin\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class TicketStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    /**
     * Statistik tidak perlu polling; data berubah saat halaman dimuat ulang.
     */
    protected static ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $query = $this->dashboardQuery($user);

        $total = (clone $query)->count();
        $open = (clone $query)->where('status', TicketStatus::Open->value)->count();
        $inProgress = (clone $query)->where('status', TicketStatus::InProgress->value)->count();
        $completed = (clone $query)
            ->whereIn('status', [TicketStatus::Resolved->value, TicketStatus::Closed->value])
            ->count();

        $newThisWeek = (clone $query)->where('created_at', '>=', now()->subDays(7))->count();
        $unassigned = (clone $query)
            ->whereNull('assigned_to')
            ->where('status', TicketStatus::Open->value)
            ->count();
        $urgentInProgress = (clone $query)
            ->where('status', TicketStatus::InProgress->value)
            ->where('priority', 'urgent')
            ->count();
        $completedThisWeek = (clone $query)
            ->whereIn('status', [TicketStatus::Resolved->value, TicketStatus::Closed->value])
            ->where('updated_at', '>=', now()->subDays(7))
            ->count();

        $primary = match (true) {
            $user->isAdmin() => Stat::make('Total Tickets', $total)
                ->description("{$newThisWeek} new in the last 7 days")
                ->icon('heroicon-o-ticket')
                ->url(TicketResource::getUrl('index')),
            $user->isStaff() => Stat::make('Assigned to Me', $total)
                ->description("{$open} still open")
                ->icon('heroicon-o-inbox-stack')
                ->url(TicketResource::getUrl('assigned')),
            default => Stat::make('My Tickets', $total)
                ->description("{$newThisWeek} created in the last 7 days")
                ->icon('heroicon-o-user')
                ->url(TicketResource::getUrl('my')),
        };

        return [
            $primary->color('primary'),

            Stat::make('Open', $open)
                ->description($unassigned > 0
                    ? "{$unassigned} unassigned"
                    : 'All open tickets have an owner')
                ->descriptionIcon($unassigned > 0 ? 'heroicon-m-exclamation-circle' : 'heroicon-m-check-circle')
                ->descriptionColor($unassigned > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-inbox')
                ->color('info'),

            Stat::make('In Progress', $inProgress)
                ->description($urgentInProgress > 0
                    ? "{$urgentInProgress} urgent ticket(s)"
                    : 'No urgent work in progress')
                ->descriptionIcon('heroicon-m-clock')
                ->descriptionColor($urgentInProgress > 0 ? 'danger' : 'gray')
                ->icon('heroicon-o-arrow-path')
                ->color('warning'),

            Stat::make('Completed', $completed)
                ->description("{$completedThisWeek} completed this week")
                ->descriptionIcon('heroicon-m-check-badge')
                ->descriptionColor('success')
                ->icon('heroicon-o-check-circle')
                ->color('success'),
        ];
    }

    /**
     * Admin melihat seluruh tiket; staff melihat tiket yang ditugaskan
     * kepadanya; reporter melihat tiket yang ia laporkan.
     */
    private function dashboardQuery(User $user): Builder
    {
        $query = Ticket::query()->visibleTo($user);

        if (! $user->isAdmin() && $user->isStaff()) {
            $query->where('assigned_to', $user->id);
        }

        return $query;
    }
}
