<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Ticket;
use Filament\Widgets\ChartWidget;

class TicketTrendChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'Ticket Activity';

    protected static ?string $description = 'Tickets created and resolved in the last 14 days';

    protected static ?string $pollingInterval = null;

    protected static ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = 'full';

    protected static ?array $options = [
        'plugins' => [
            'legend' => [
                'display' => true,
                'position' => 'bottom',
            ],
        ],
        'scales' => [
            'y' => [
                'beginAtZero' => true,
                'ticks' => [
                    'precision' => 0,
                ],
            ],
            'x' => [
                'grid' => [
                    'display' => false,
                ],
            ],
        ],
    ];

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $start = now()->subDays(13)->startOfDay();
        $user = auth()->user();

        $created = Ticket::query()
            ->visibleTo($user)
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $resolved = Ticket::query()
            ->visibleTo($user)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', $start)
            ->selectRaw('DATE(resolved_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $days = collect(range(0, 13))->map(
            fn (int $offset) => $start->copy()->addDays($offset)
        );

        return [
            'datasets' => [
                [
                    'label' => 'Created',
                    'data' => $days->map(
                        fn ($day): int => (int) ($created[$day->toDateString()] ?? 0)
                    )->all(),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.15)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
                [
                    'label' => 'Resolved',
                    'data' => $days->map(
                        fn ($day): int => (int) ($resolved[$day->toDateString()] ?? 0)
                    )->all(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.12)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $days->map(
                fn ($day): string => $day->format('d M')
            )->all(),
        ];
    }
}
