<?php

namespace App\Filament\Admin\Resources\TicketResource\Pages;

use App\Models\Ticket;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListMyTickets extends ListTickets
{
    protected static ?string $navigationLabel = 'My Tickets';

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationGroup = 'Ticket Management';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'My Tickets';

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return auth()->check();
    }

    public function table(Table $table): Table
    {
        return parent::table($table)->modifyQueryUsing(
            fn (Builder $query): Builder => $query->where('reporter_id', auth()->id())
        );
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        return $user
            ? (string) Ticket::query()->visibleTo($user)->where('reporter_id', $user->id)->count()
            : null;
    }
}
