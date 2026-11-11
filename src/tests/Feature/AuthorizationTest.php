<?php

use App\Models\Ticket;
use App\Models\User;

it('forbids a user without any role from accessing the panel', function () {
    $user = User::factory()->create(); // tanpa role

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('hides a foreign ticket from a reporter (404, bukan 403 — agar keberadaan tiket tidak bocor)', function () {
    $reporter = makeUserWithRole('user');
    $someoneElse = User::factory()->create();
    $someoneElse->assignRole('user');

    $foreignTicket = Ticket::factory()->reportedBy($someoneElse)->create();

    $this->actingAs($reporter)
        ->get('/admin/tickets/' . $foreignTicket->id)
        ->assertNotFound();
});

it('does not list foreign tickets to a reporter', function () {
    $reporter = makeUserWithRole('user');
    $someoneElse = User::factory()->create();
    $someoneElse->assignRole('user');

    Ticket::factory()->reportedBy($someoneElse)->create(['subject' => 'Tiket rahasia orang lain']);

    $this->actingAs($reporter)
        ->get('/admin/tickets')
        ->assertOk()
        ->assertDontSee('Tiket rahasia orang lain');
});

it('lets a staff member open an assigned ticket', function () {
    $staff = makeUserWithRole('staff');
    $ticket = Ticket::factory()->assigned($staff->id)->create();

    $this->actingAs($staff)
        ->get('/admin/tickets/' . $ticket->id)
        ->assertOk();
});

it('hides an unassigned ticket from another staff member (404)', function () {
    $staff = makeUserWithRole('staff');
    $otherStaff = makeUserWithRole('staff');
    $ticket = Ticket::factory()->assigned($otherStaff->id)->create();

    $this->actingAs($staff)
        ->get('/admin/tickets/' . $ticket->id)
        ->assertNotFound();
});

it('forbids a reporter from editing their own ticket', function () {
    $reporter = makeUserWithRole('user');
    $ticket = Ticket::factory()->reportedBy($reporter)->create();

    $this->actingAs($reporter)
        ->get('/admin/tickets/' . $ticket->id . '/edit')
        ->assertForbidden();
});

it('lets a staff member edit an assigned ticket', function () {
    $staff = makeUserWithRole('staff');
    $ticket = Ticket::factory()->assigned($staff->id)->create();

    $this->actingAs($staff)
        ->get('/admin/tickets/' . $ticket->id . '/edit')
        ->assertOk();
});

it('lets an admin open any ticket', function () {
    $admin = makeUserWithRole('admin');
    $ticket = Ticket::factory()->create();

    $this->actingAs($admin)
        ->get('/admin/tickets/' . $ticket->id)
        ->assertOk();
});

it('forbids non-admin users from managing categories', function () {
    $staff = makeUserWithRole('staff');

    $this->actingAs($staff)
        ->get('/admin/ticket-categories/create')
        ->assertForbidden();
});

it('forbids non-admin users from managing users', function () {
    $staff = makeUserWithRole('staff');

    $this->actingAs($staff)
        ->get('/admin/users')
        ->assertForbidden();
});

it('lets an admin manage users and categories', function () {
    $admin = makeUserWithRole('admin');

    $this->actingAs($admin)->get('/admin/users')->assertOk();
    $this->actingAs($admin)->get('/admin/ticket-categories/create')->assertOk();
});

it('shows all ticket navigation items to an admin', function () {
    $admin = makeUserWithRole('admin');

    $this->actingAs($admin)->get('/admin')
        ->assertOk()
        ->assertSee('All Tickets')
        ->assertSee('My Tickets')
        ->assertSee('Assigned Tickets');
});

it('hides the all tickets item from staff', function () {
    $staff = makeUserWithRole('staff');

    $this->actingAs($staff)->get('/admin')
        ->assertOk()
        ->assertDontSee('All Tickets')
        ->assertSee('My Tickets')
        ->assertSee('Assigned Tickets');
});

it('hides all and assigned ticket items from a reporter', function () {
    $reporter = makeUserWithRole('user');

    $this->actingAs($reporter)->get('/admin')
        ->assertOk()
        ->assertDontSee('All Tickets')
        ->assertSee('My Tickets')
        ->assertDontSee('Assigned Tickets');
});

it('forbids a reporter from opening the assigned tickets page', function () {
    $reporter = makeUserWithRole('user');

    $this->actingAs($reporter)
        ->get('/admin/tickets/assigned')
        ->assertForbidden();
});

it('allows comments only on tickets visible to the user', function () {
    $reporter = makeUserWithRole('user');
    $otherReporter = makeUserWithRole('user');
    $ownTicket = Ticket::factory()->reportedBy($reporter)->create();
    $foreignTicket = Ticket::factory()->reportedBy($otherReporter)->create();

    expect($reporter->can('comment', $ownTicket))->toBeTrue()
        ->and($reporter->can('comment', $foreignTicket))->toBeFalse();
});

it('allows only admins to delete tickets', function () {
    $admin = makeUserWithRole('admin');
    $staff = makeUserWithRole('staff');
    $ticket = Ticket::factory()->assigned($staff->id)->create();

    expect($admin->can('delete', $ticket))->toBeTrue()
        ->and($staff->can('delete', $ticket))->toBeFalse();
});
