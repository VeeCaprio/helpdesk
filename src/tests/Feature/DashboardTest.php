<?php

use App\Filament\Admin\Widgets\LatestAccessLogs;
use App\Filament\Admin\Widgets\RecentTicketsTable;
use App\Filament\Admin\Widgets\TicketPriorityChart;
use App\Filament\Admin\Widgets\TicketStatsOverview;
use App\Filament\Admin\Widgets\TicketStatusChart;
use App\Filament\Admin\Widgets\TicketTrendChart;
use App\Models\Ticket;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Livewire\Livewire;

/**
 * Ambil pasangan label => nilai dari widget statistik tanpa bergantung markup HTML.
 *
 * @return array<string, mixed>
 */
function dashboardStats(User $user): array
{
    test()->actingAs($user);

    $widget = new TicketStatsOverview;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    return collect($stats)
        ->mapWithKeys(fn ($stat): array => [(string) $stat->getLabel() => $stat->getValue()])
        ->all();
}

/**
 * @return array<string, mixed>
 */
function chartData(ChartWidget $widget): array
{
    return (new ReflectionMethod($widget, 'getData'))->invoke($widget);
}

it('shows all tickets in the admin statistics', function () {
    $admin = makeUserWithRole('admin');
    $staff = makeUserWithRole('staff');
    $reporter = makeUserWithRole('user');

    Ticket::factory()->reportedBy($reporter)->create(['status' => 'open']);
    Ticket::factory()->reportedBy($reporter)->assigned($staff->id)->create(['status' => 'resolved']);
    Ticket::factory()->reportedBy($reporter)->assigned($staff->id)->create(['status' => 'in_progress']);

    $stats = dashboardStats($admin);

    expect($stats['Total Tickets'])->toBe(3)
        ->and($stats['Open'])->toBe(1)
        ->and($stats['In Progress'])->toBe(1)
        ->and($stats['Completed'])->toBe(1);
});

it('scopes staff statistics to their assigned tickets', function () {
    $me = makeUserWithRole('staff');
    $otherStaff = makeUserWithRole('staff');
    $reporter = makeUserWithRole('user');

    Ticket::factory()->reportedBy($reporter)->assigned($me->id)->create(['status' => 'open']);
    Ticket::factory()->reportedBy($reporter)->assigned($me->id)->create(['status' => 'in_progress']);
    Ticket::factory()->reportedBy($reporter)->assigned($otherStaff->id)->create(['status' => 'open']);

    $stats = dashboardStats($me);

    expect($stats['Assigned to Me'])->toBe(2)
        ->and($stats['Open'])->toBe(1)
        ->and($stats)->not->toHaveKey('Total Tickets');
});

it('scopes reporter statistics to their own tickets', function () {
    $me = makeUserWithRole('user');
    $otherReporter = makeUserWithRole('user');

    Ticket::factory()->reportedBy($me)->create(['status' => 'open']);
    Ticket::factory()->reportedBy($me)->create(['status' => 'resolved']);
    Ticket::factory()->reportedBy($otherReporter)->create(['status' => 'open']);

    $stats = dashboardStats($me);

    expect($stats['My Tickets'])->toBe(2)
        ->and($stats['Open'])->toBe(1)
        ->and($stats)->not->toHaveKey('Assigned to Me');
});

it('scopes the status chart to visible tickets', function () {
    $me = makeUserWithRole('user');
    $otherReporter = makeUserWithRole('user');

    Ticket::factory()->reportedBy($me)->create(['status' => 'open']);
    Ticket::factory()->reportedBy($me)->create(['status' => 'open']);
    Ticket::factory()->reportedBy($me)->create(['status' => 'resolved']);
    Ticket::factory()->reportedBy($otherReporter)->create(['status' => 'open']);

    test()->actingAs($me);

    $data = chartData(new TicketStatusChart);

    expect($data['labels'])->toBe(['Open', 'In Progress', 'Pending', 'Resolved', 'Closed'])
        ->and($data['datasets'][0]['data'])->toBe([2, 0, 0, 1, 0]);
});

it('shows only visible tickets in the recent tickets widget', function () {
    $me = makeUserWithRole('staff');
    $otherStaff = makeUserWithRole('staff');
    $reporter = makeUserWithRole('user');

    Ticket::factory()->reportedBy($reporter)->assigned($me->id)
        ->create(['subject' => 'Tiket untuk saya']);
    Ticket::factory()->reportedBy($reporter)->assigned($otherStaff->id)
        ->create(['subject' => 'Tiket untuk petugas lain']);

    $this->actingAs($me);

    Livewire::test(RecentTicketsTable::class)
        ->assertSee('Tiket untuk saya')
        ->assertDontSee('Tiket untuk petugas lain');
});

it('shows only own tickets in the reporter recent tickets widget', function () {
    $me = makeUserWithRole('user');
    $otherReporter = makeUserWithRole('user');

    Ticket::factory()->reportedBy($me)->create(['subject' => 'Tiket milik saya']);
    Ticket::factory()->reportedBy($otherReporter)->create(['subject' => 'Tiket milik orang lain']);

    $this->actingAs($me);

    Livewire::test(RecentTicketsTable::class)
        ->assertSee('Tiket milik saya')
        ->assertDontSee('Tiket milik orang lain');
});

it('renders all dashboard charts for a reporter', function () {
    $me = makeUserWithRole('user');

    Ticket::factory()->reportedBy($me)->create();

    $this->actingAs($me);

    Livewire::test(TicketTrendChart::class)->assertOk();
    Livewire::test(TicketStatusChart::class)->assertOk();
    Livewire::test(TicketPriorityChart::class)->assertOk();
});

it('shows the overlook widget only to admins', function () {
    $admin = makeUserWithRole('admin');
    $staff = makeUserWithRole('staff');
    $reporter = makeUserWithRole('user');

    $this->actingAs($admin)->get('/admin')
        ->assertOk()
        ->assertSee('OverlookWidget');

    $this->actingAs($staff)->get('/admin')
        ->assertOk()
        ->assertDontSee('OverlookWidget');

    $this->actingAs($reporter)->get('/admin')
        ->assertOk()
        ->assertDontSee('OverlookWidget');
});

it('shows access logs only to admins', function () {
    $admin = makeUserWithRole('admin');
    $staff = makeUserWithRole('staff');
    $reporter = makeUserWithRole('user');

    $this->actingAs($admin);
    expect(LatestAccessLogs::canView())->toBeTrue();

    $this->actingAs($staff);
    expect(LatestAccessLogs::canView())->toBeFalse();

    $this->actingAs($reporter);
    expect(LatestAccessLogs::canView())->toBeFalse();
});
