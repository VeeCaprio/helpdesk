<?php

namespace App\Observers;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;

/**
 * Mencatat riwayat penting pada tiket:
 * pembuatan, perubahan status, prioritas, petugas, kategori, dan penyelesaian.
 *
 * PENTING: riwayat HANYA ditambahkan (tidak pernah menimpa/menghapus data lama).
 */
class TicketObserver
{
    public function created(Ticket $ticket): void
    {
        $ticket->recordHistory(
            Ticket::ACTIVITY_CREATED,
            ['description' => 'Tiket dibuat oleh ' . ($ticket->reporter?->name ?? 'sistem')]
        );
    }

    public function updated(Ticket $ticket): void
    {
        $changes = $ticket->getChanges();
        $original = $ticket->getOriginal();

        if (array_key_exists('status', $changes)) {
            $old = TicketStatus::from($original['status'] ?? TicketStatus::Open->value);
            $new = TicketStatus::from($changes['status']);

            $ticket->recordHistory(Ticket::ACTIVITY_STATUS_CHANGED, [
                'old_value' => $old->label(),
                'new_value' => $new->label(),
            ]);

            if ($new === TicketStatus::Resolved) {
                $ticket->recordHistory(Ticket::ACTIVITY_RESOLVED, [
                    'description' => 'Tiket ditandai selesai oleh ' . (auth()->user()?->name ?? 'sistem'),
                ]);
            }
        }

        if (array_key_exists('priority', $changes)) {
            $old = TicketPriority::from($original['priority'] ?? TicketPriority::Medium->value);
            $new = TicketPriority::from($changes['priority']);

            $ticket->recordHistory(Ticket::ACTIVITY_PRIORITY_CHANGED, [
                'old_value' => $old->label(),
                'new_value' => $new->label(),
            ]);
        }

        if (array_key_exists('assigned_to', $changes)) {
            $ticket->recordHistory(Ticket::ACTIVITY_ASSIGNEE_CHANGED, [
                'old_value' => $this->userName($original['assigned_to'] ?? null),
                'new_value' => $this->userName($changes['assigned_to'] ?? null),
            ]);
        }

        if (array_key_exists('category_id', $changes)) {
            $ticket->recordHistory(Ticket::ACTIVITY_CATEGORY_CHANGED, [
                'old_value' => $this->categoryName($original['category_id'] ?? null),
                'new_value' => $this->categoryName($changes['category_id'] ?? null),
            ]);
        }
    }

    private function userName(?int $id): string
    {
        if (! $id) {
            return 'Belum ditugaskan';
        }

        return User::query()->whereKey($id)->value('name') ?? 'Pengguna terhapus';
    }

    private function categoryName(?int $id): string
    {
        if (! $id) {
            return '-';
        }

        return TicketCategory::query()->whereKey($id)->value('name') ?? 'Kategori terhapus';
    }
}
