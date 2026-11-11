<?php

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Livewire\Livewire;

it('lists all tickets on the All Tickets page', function () {
    Ticket::factory()->create(['subject' => 'Printer tidak bisa mencetak']);

    $user = makeUserWithRole();

    $this->actingAs($user)
        ->get('/admin/tickets')
        ->assertOk()
        ->assertSee('Printer tidak bisa mencetak');
});

it('shows only tickets reported by the current user on My Tickets', function () {
    $me = makeUserWithRole();
    $other = User::factory()->create();

    Ticket::factory()->reportedBy($me)->create(['subject' => 'Tiket milik saya']);
    Ticket::factory()->reportedBy($other)->create(['subject' => 'Tiket orang lain']);

    $this->actingAs($me)
        ->get('/admin/tickets/my')
        ->assertOk()
        ->assertSee('Tiket milik saya')
        ->assertDontSee('Tiket orang lain');
});

it('shows only tickets assigned to the current user on Assigned Tickets', function () {
    $me = makeUserWithRole();
    $other = User::factory()->create();

    Ticket::factory()->assigned($me->id)->create(['subject' => 'Tiket yang saya tangani']);
    Ticket::factory()->assigned($other->id)->create(['subject' => 'Tiket orang lain']);

    $this->actingAs($me)
        ->get('/admin/tickets/assigned')
        ->assertOk()
        ->assertSee('Tiket yang saya tangani')
        ->assertDontSee('Tiket orang lain');
});

it('renders the ticket detail page', function () {
    $ticket = Ticket::factory()->create();

    $user = makeUserWithRole();

    $this->actingAs($user)
        ->get('/admin/tickets/' . $ticket->id)
        ->assertOk()
        ->assertSee($ticket->ticket_number)
        ->assertSee('People & Timeline');
});

it('generates a unique ticket number automatically', function () {
    $first = Ticket::factory()->create();
    $second = Ticket::factory()->create();

    expect($first->ticket_number)
        ->toStartWith('TKT-')
        ->not->toBe($second->ticket_number);
});

it('generates sequential ticket numbers for the same day', function () {
    $date = now()->format('Ymd');

    $first = Ticket::factory()->create();
    $second = Ticket::factory()->create();
    $third = Ticket::factory()->create();

    expect($first->ticket_number)->toBe("TKT-{$date}-0001")
        ->and($second->ticket_number)->toBe("TKT-{$date}-0002")
        ->and($third->ticket_number)->toBe("TKT-{$date}-0003");
});

it('forces the reporter and open status when a reporter creates a ticket', function () {
    $reporter = makeUserWithRole('user');
    makeUserWithRole('user');

    Livewire::actingAs($reporter)
        ->test(\App\Filament\Admin\Resources\TicketResource\Pages\CreateTicket::class)
        ->fillForm([
            'subject' => 'Akses aplikasi baru',
            'description' => 'Saya membutuhkan akses ke aplikasi baru.',
            'category_id' => TicketCategory::factory()->create()->id,
            'priority' => 'medium',
        ])
        ->call('create')
        ->assertHasNoErrors();

    $ticket = Ticket::where('subject', 'Akses aplikasi baru')->firstOrFail();

    expect($ticket->reporter_id)->toBe($reporter->id)
        ->and($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->assigned_to)->toBeNull();
});

it('records a history entry when a ticket is created from the panel', function () {
    $user = makeUserWithRole();

    Livewire::actingAs($user)
        ->test(\App\Filament\Admin\Resources\TicketResource\Pages\CreateTicket::class)
        ->fillForm([
            'subject' => 'Laptop tidak menyala',
            'description' => 'Laptop mati total saat dinyalakan.',
            'category_id' => \App\Models\TicketCategory::factory()->create()->id,
            'priority' => 'high',
            'status' => 'open',
            'reporter_id' => $user->id,
        ])
        ->call('create')
        ->assertHasNoErrors();

    $ticket = Ticket::where('subject', 'Laptop tidak menyala')->first();

    expect($ticket)->not->toBeNull()
        ->and($ticket->ticket_number)->toStartWith('TKT-')
        ->and($ticket->histories()->count())->toBe(1)
        ->and($ticket->histories()->first()->activity)->toBe(Ticket::ACTIVITY_CREATED);
});
