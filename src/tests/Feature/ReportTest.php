<?php

use App\Filament\Admin\Pages\StaffPerformance;
use App\Filament\Admin\Pages\TicketReport;
use App\Models\Ticket;
use Livewire\Livewire;

it('lets an admin open the ticket report and staff performance pages', function () {
    $admin = makeUserWithRole('admin');

    $this->actingAs($admin)->get('/admin/reports/tickets')
        ->assertOk()
        ->assertSee('Ticket Report');

    $this->actingAs($admin)->get('/admin/reports/staff-performance')
        ->assertOk()
        ->assertSee('Staff Performance');
});

it('forbids staff and reporters from opening reports', function () {
    $staff = makeUserWithRole('staff');
    $reporter = makeUserWithRole('user');

    foreach ([$staff, $reporter] as $user) {
        $this->actingAs($user)->get('/admin/reports/tickets')->assertForbidden();
        $this->actingAs($user)->get('/admin/reports/staff-performance')->assertForbidden();
    }
});

it('shows report navigation to admins', function () {
    $admin = makeUserWithRole('admin');

    $this->actingAs($admin)->get('/admin')
        ->assertOk()
        ->assertSee('Ticket Report')
        ->assertSee('Staff Performance');
});

it('hides report navigation from staff', function () {
    $staff = makeUserWithRole('staff');

    $this->actingAs($staff)->get('/admin')
        ->assertOk()
        ->assertDontSee('Ticket Report')
        ->assertDontSee('Staff Performance');
});

it('hides report navigation from reporters', function () {
    $reporter = makeUserWithRole('user');

    $this->actingAs($reporter)->get('/admin')
        ->assertOk()
        ->assertDontSee('Ticket Report')
        ->assertDontSee('Staff Performance');
});

it('filters the ticket report by status and updates the summary', function () {
    $admin = makeUserWithRole('admin');
    $reporter = makeUserWithRole('user');

    $open = Ticket::factory()->reportedBy($reporter)
        ->create(['subject' => 'Tiket open untuk laporan', 'status' => 'open']);
    $resolved = Ticket::factory()->reportedBy($reporter)
        ->create(['subject' => 'Tiket resolved untuk laporan', 'status' => 'resolved']);

    $component = Livewire::actingAs($admin)->test(TicketReport::class);

    $component
        ->assertCanSeeTableRecords([$open, $resolved])
        ->filterTable('status', ['open'])
        ->assertCanSeeTableRecords([$open])
        ->assertCanNotSeeTableRecords([$resolved]);

    $summary = collect($component->instance()->summary())->keyBy('label');

    expect($summary['Tickets']['value'])->toBe(1)
        ->and($summary['Open']['value'])->toBe(1)
        ->and($summary['Completed']['value'])->toBe(0);
});

it('filters the ticket report by date range', function () {
    $admin = makeUserWithRole('admin');
    $reporter = makeUserWithRole('user');

    $recent = Ticket::factory()->reportedBy($reporter)
        ->create(['subject' => 'Tiket baru', 'status' => 'open']);
    $old = Ticket::factory()->reportedBy($reporter)
        ->create(['subject' => 'Tiket lama', 'status' => 'open']);

    $old->timestamps = false;
    $old->forceFill([
        'created_at' => now()->subDays(30),
        'updated_at' => now()->subDays(30),
    ])->saveQuietly();
    $old->timestamps = true;

    Livewire::actingAs($admin)->test(TicketReport::class)
        ->filterTable('created_at', [
            'from' => now()->subDays(7)->toDateString(),
            'to' => now()->toDateString(),
        ])
        ->assertCanSeeTableRecords([$recent])
        ->assertCanNotSeeTableRecords([$old]);
});

it('shows per-staff workload and resolution metrics', function () {
    $admin = makeUserWithRole('admin');
    $staff = makeUserWithRole('staff');
    $otherStaff = makeUserWithRole('staff');
    $reporter = makeUserWithRole('user');

    Ticket::factory()->reportedBy($reporter)->assigned($staff->id)->create(['status' => 'open']);
    Ticket::factory()->reportedBy($reporter)->assigned($staff->id)->create(['status' => 'in_progress']);
    Ticket::factory()->reportedBy($reporter)->assigned($staff->id)->create(['status' => 'resolved']);
    Ticket::factory()->reportedBy($reporter)->assigned($otherStaff->id)->create(['status' => 'open']);

    Livewire::actingAs($admin)->test(StaffPerformance::class)
        ->assertCanSeeTableRecords([$staff, $otherStaff])
        ->assertTableColumnStateSet('assigned_total', 3, $staff->id)
        ->assertTableColumnStateSet('active_total', 2, $staff->id)
        ->assertTableColumnStateSet('completed_total', 1, $staff->id)
        ->assertTableColumnStateSet('resolution_rate', '33%', $staff->id)
        ->assertTableColumnStateSet('assigned_total', 1, $otherStaff->id)
        ->assertTableColumnStateSet('resolution_rate', '0%', $otherStaff->id);
});
