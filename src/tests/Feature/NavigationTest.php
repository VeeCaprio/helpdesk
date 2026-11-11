<?php

use App\Models\TicketCategory;

it('redirects guest to the login page', function () {
    $this->get('/admin')->assertRedirect();
});

it('shows the login page', function () {
    $this->get('/admin/login')->assertOk()->assertSee('Login');
});

it('renders the dashboard for an authenticated user', function () {
    $user = makeUserWithRole();

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk()
        ->assertSee('Dashboard');
});

it('renders the ticket categories page with seeded categories', function () {
    TicketCategory::factory()->create(['name' => 'Technical Support']);

    $user = makeUserWithRole();

    $this->actingAs($user)
        ->get('/admin/ticket-categories')
        ->assertOk()
        ->assertSee('Technical Support')
        ->assertSee('Categories');
});

it('does not let a guest open the categories page', function () {
    $this->get('/admin/ticket-categories')->assertRedirect();
});
