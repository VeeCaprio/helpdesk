<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Ticket extends Model
{
    /** @use HasFactory<\Database\Factories\TicketFactory> */
    use HasFactory;

    /* ---------------------------------------------------------------
     | Aktivitas yang dicatat ke ticket_histories
     --------------------------------------------------------------- */
    public const ACTIVITY_CREATED = 'created';
    public const ACTIVITY_STATUS_CHANGED = 'status_changed';
    public const ACTIVITY_PRIORITY_CHANGED = 'priority_changed';
    public const ACTIVITY_ASSIGNEE_CHANGED = 'assignee_changed';
    public const ACTIVITY_CATEGORY_CHANGED = 'category_changed';
    public const ACTIVITY_RESOLVED = 'resolved';

    protected $fillable = [
        'ticket_number',
        'subject',
        'description',
        'category_id',
        'priority',
        'status',
        'reporter_id',
        'assigned_to',
        'attachment',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Ticket number dibuat otomatis & unik saat tiket dibuat.
        static::creating(function (Ticket $ticket) {
            if (blank($ticket->ticket_number)) {
                $ticket->ticket_number = static::generateTicketNumber();
            }
        });

        // resolved_at diisi otomatis saat status menjadi Resolved.
        static::saving(function (Ticket $ticket) {
            if ($ticket->status === TicketStatus::Resolved && is_null($ticket->resolved_at)) {
                $ticket->resolved_at = now();
            }

            if (in_array($ticket->status, [TicketStatus::Open, TicketStatus::InProgress, TicketStatus::Pending], true)) {
                $ticket->resolved_at = null;
            }
        });
    }

    /* ---------------------------------------------------------------
     | Relasi
     --------------------------------------------------------------- */

    /** @return BelongsTo<TicketCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return HasMany<TicketComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class, 'ticket_id');
    }

    /** @return HasMany<TicketHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(TicketHistory::class, 'ticket_id');
    }

    /* ---------------------------------------------------------------
     | Scope & otorisasi
     --------------------------------------------------------------- */

    /**
     * Batasi query tiket sesuai role:
     * - admin : semua tiket
     * - staff : tiket yang ditugaskan kepadanya + yang ia laporkan
     * - user  : hanya tiket yang ia laporkan
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isStaff()) {
            return $query->where(function (Builder $query) use ($user): void {
                $query->where($this->qualifyColumn('assigned_to'), $user->id)
                    ->orWhere($this->qualifyColumn('reporter_id'), $user->id);
            });
        }

        if ($user->isReporter()) {
            return $query->where($this->qualifyColumn('reporter_id'), $user->id);
        }

        return $query->whereRaw('1 = 0');
    }

    public function isVisibleTo(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return (int) $this->assigned_to === (int) $user->id
                || (int) $this->reporter_id === (int) $user->id;
        }

        return $user->isReporter() && (int) $this->reporter_id === (int) $user->id;
    }

    /* ---------------------------------------------------------------
     | Helper
     --------------------------------------------------------------- */

    /**
     * Menghasilkan ticket number unik: TKT-YYYYMMDD-0001.
     *
     * Sequence disimpan per tanggal di tabel ticket_sequences dan dikunci
     * dengan lockForUpdate. Unique index pada ticket_number menjadi lapisan
     * pengaman terakhir bila terjadi benturan.
     */
    public static function generateTicketNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = 'TKT-' . $date . '-';

        return DB::transaction(function () use ($date, $prefix): string {
            DB::table('ticket_sequences')->insertOrIgnore([
                'sequence_date' => $date,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('ticket_sequences')
                ->where('sequence_date', $date)
                ->lockForUpdate()
                ->first();

            $next = ((int) $sequence->last_number) + 1;

            DB::table('ticket_sequences')
                ->where('sequence_date', $date)
                ->update([
                    'last_number' => $next,
                    'updated_at' => now(),
                ]);

            return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        }, 5);
    }

    /**
     * Catat riwayat perubahan penting pada tiket.
     *
     * @param  array{user_id?: int|null, old_value?: string|null, new_value?: string|null, description?: string|null}  $context
     */
    public function recordHistory(string $activity, array $context = []): TicketHistory
    {
        return $this->histories()->create(array_merge([
            'user_id' => auth()->id(),
            'activity' => $activity,
        ], $context));
    }

    /**
     * Apakah tiket masih dalam penanganan?
     */
    public function isOpenForWork(): bool
    {
        return in_array($this->status, [TicketStatus::Open, TicketStatus::InProgress, TicketStatus::Pending], true);
    }
}
