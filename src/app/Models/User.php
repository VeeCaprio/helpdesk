<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_STAFF = 'staff';

    public const ROLE_USER = 'user';

    /** @var list<string> */
    public const ROLES = [self::ROLE_ADMIN, self::ROLE_STAFF, self::ROLE_USER];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'avatar_url',
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /* ---------------------------------------------------------------
     | Relasi HelpDesk
     --------------------------------------------------------------- */

    /** @return HasMany<Ticket, $this> Tiket yang dilaporkan oleh user ini. */
    public function reportedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'reporter_id');
    }

    /** @return HasMany<Ticket, $this> Tiket yang ditugaskan kepada user ini. */
    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    /** @return HasMany<TicketComment, $this> */
    public function ticketComments(): HasMany
    {
        return $this->hasMany(TicketComment::class, 'user_id');
    }

    /** @return HasMany<TicketHistory, $this> */
    public function ticketHistories(): HasMany
    {
        return $this->hasMany(TicketHistory::class, 'user_id');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    /**
     * Apakah user termasuk petugas (staff/admin) yang boleh menangani tiket?
     */
    public function isStaff(): bool
    {
        return $this->hasAnyRole([self::ROLE_ADMIN, self::ROLE_STAFF]);
    }

    public function isReporter(): bool
    {
        return $this->hasRole(self::ROLE_USER);
    }

    public function getFilamentAvatarUrl(): ?string
    {
        if ($this->avatar_url) {
            return asset('storage/' . $this->avatar_url);
        } else {
            $hash = md5(strtolower(trim($this->email)));

            return 'https://www.gravatar.com/avatar/' . $hash . '?d=mp&r=g&s=250';
        }
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Hanya user dengan salah satu dari tiga role bisnis HelpDesk.
        return $this->hasAnyRole(self::ROLES);
    }
}
