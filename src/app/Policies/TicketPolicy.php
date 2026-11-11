<?php

namespace App\Policies;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TicketPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_ticket');
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $user->can('view_ticket') && $ticket->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('create_ticket');
    }

    public function update(User $user, Ticket $ticket): bool
    {
        if (! $user->can('update_ticket') || ! $ticket->isVisibleTo($user)) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->isStaff()
            && ((int) $ticket->assigned_to === (int) $user->id
                || (int) $ticket->reporter_id === (int) $user->id);
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() && $user->can('delete_ticket');
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin() && $user->can('delete_any_ticket');
    }

    public function comment(User $user, Ticket $ticket): bool
    {
        if (! $user->can('view_ticket') || ! $ticket->isVisibleTo($user)) {
            return false;
        }

        return $user->isAdmin() || $ticket->status !== TicketStatus::Closed;
    }

    public function forceDelete(User $user, Ticket $ticket): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Ticket $ticket): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, Ticket $ticket): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}
