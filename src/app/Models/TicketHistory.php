<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketHistory extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'activity',
        'old_value',
        'new_value',
        'description',
    ];

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Label human-readable untuk jenis aktivitas.
     */
    public function activityLabel(): string
    {
        return match ($this->activity) {
            Ticket::ACTIVITY_CREATED => 'Tiket dibuat',
            Ticket::ACTIVITY_STATUS_CHANGED => 'Status diubah',
            Ticket::ACTIVITY_PRIORITY_CHANGED => 'Prioritas diubah',
            Ticket::ACTIVITY_ASSIGNEE_CHANGED => 'Petugas ditugaskan',
            Ticket::ACTIVITY_CATEGORY_CHANGED => 'Kategori diubah',
            Ticket::ACTIVITY_RESOLVED => 'Tiket diselesaikan',
            default => ucfirst(str_replace('_', ' ', $this->activity)),
        };
    }
}
