<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Filament\Widgets\ChartWidget;

class TicketStatusChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'Tickets by Status';

    protected static ?string $description = 'Current status distribution';

    protected static ?string $pollingInterval = null;

    protected static ?string $maxHeight = '280px';

    protected static ?array $options = [
        'plugins' => [
            'legend' => [
                'display' => true,
                'position' => 'bottom',
            ],
        ],
        'cutout' => '65%',
        // Filament menambahkan skala x/y ke semua ChartWidget. Untuk
        // doughnut, skala tersebut tidak relevan dan membuat sumbu 0–1
        // muncul di sekitar grafik, jadi sembunyikan keduanya.
        'scales' => [
            'x' => ['display' => false],
            'y' => ['display' => false],
        ],
    ];

    protected function getType(): string
    {
        return 'doughnut';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $counts = Ticket::query()
            ->visibleTo(auth()->user())
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $colors = [
            TicketStatus::Open->value => '#3b82f6',
            TicketStatus::InProgress->value => '#f59e0b',
            TicketStatus::Pending->value => '#6b7280',
            TicketStatus::Resolved->value => '#10b981',
            TicketStatus::Closed->value => '#ef4444',
        ];

        $statuses = TicketStatus::cases();

        return [
            'datasets' => [
                [
                    'data' => array_map(
                        fn (TicketStatus $status): int => (int) ($counts[$status->value] ?? 0),
                        $statuses,
                    ),
                    'backgroundColor' => array_map(
                        fn (TicketStatus $status): string => $colors[$status->value],
                        $statuses,
                    ),
                    'borderWidth' => 0,
                ],
            ],
            'labels' => array_map(
                fn (TicketStatus $status): string => $status->label(),
                $statuses,
            ),
        ];
    }
}
