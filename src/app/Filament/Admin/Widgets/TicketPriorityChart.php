<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\TicketPriority;
use App\Models\Ticket;
use Filament\Widgets\ChartWidget;

class TicketPriorityChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected static ?string $heading = 'Tickets by Priority';

    protected static ?string $description = 'Workload distribution by urgency';

    protected static ?string $pollingInterval = null;

    protected static ?string $maxHeight = '280px';

    protected static ?array $options = [
        'plugins' => [
            'legend' => [
                'display' => false,
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
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $counts = Ticket::query()
            ->visibleTo(auth()->user())
            ->selectRaw('priority, COUNT(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority');

        $colors = [
            TicketPriority::Low->value => '#6b7280',
            TicketPriority::Medium->value => '#3b82f6',
            TicketPriority::High->value => '#f97316',
            TicketPriority::Urgent->value => '#ef4444',
        ];

        $priorities = TicketPriority::cases();

        return [
            'datasets' => [
                [
                    'label' => 'Tickets',
                    'data' => array_map(
                        fn (TicketPriority $priority): int => (int) ($counts[$priority->value] ?? 0),
                        $priorities,
                    ),
                    'backgroundColor' => array_map(
                        fn (TicketPriority $priority): string => $colors[$priority->value],
                        $priorities,
                    ),
                    'borderRadius' => 6,
                ],
            ],
            'labels' => array_map(
                fn (TicketPriority $priority): string => $priority->label(),
                $priorities,
            ),
        ];
    }
}
