<?php

namespace App\Filament\Admin\Resources\TicketResource\Pages;

use App\Models\Ticket;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListAssignedTickets extends ListTickets
{
    protected static ?string $navigationLabel = 'Assigned Tickets';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Ticket Management';

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'Assigned Tickets';

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    protected function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->isStaff(), 403);
    }

    public function table(Table $table): Table
    {
        return parent::table($table)->modifyQueryUsing(
            fn (Builder $query): Builder => $query->where('assigned_to', auth()->id())
        );
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        return $user
            ? (string) Ticket::query()->visibleTo($user)->where('assigned_to', $user->id)->count()
            : null;
    }
}
