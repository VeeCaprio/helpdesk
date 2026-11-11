<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function makeUserWithRole(string $role = User::ROLE_ADMIN): User
{
    if (! in_array($role, User::ROLES, true)) {
        throw new InvalidArgumentException("Role [{$role}] bukan role bisnis HelpDesk.");
    }

    (new RoleSeeder)->run();
    (new RolePermissionSeeder)->run();

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}
