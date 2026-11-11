<?php

namespace App\Filament\Admin\Resources;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Filament\Admin\Resources\TicketResource\Pages;
use App\Filament\Admin\Resources\TicketResource\RelationManagers;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Ticket Management';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'All Tickets';

    protected static ?string $modelLabel = 'Ticket';

    protected static ?string $pluralModelLabel = 'Tickets';

    protected static ?string $recordTitleAttribute = 'subject';

    public static function getNavigationLabel(): string
    {
        return auth()->user()?->isAdmin() ? 'All Tickets' : 'Tickets';
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Staff & reporter memakai halaman My Tickets / Assigned Tickets.
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        return $user
            ? (string) Ticket::query()->visibleTo($user)->count()
            : null;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['category', 'reporter', 'assignee']);

        return static::scopeQueryToUser($query, auth()->user());
    }

    /**
     * Batasi daftar tiket sesuai role pengguna melalui scope di model.
     */
    public static function scopeQueryToUser(Builder $query, ?User $user): Builder
    {
        return $query->visibleTo($user);
    }

    /* ---------------------------------------------------------------
     | Form
     --------------------------------------------------------------- */

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Ticket Information')
                    ->schema([
                        Forms\Components\TextInput::make('ticket_number')
                            ->label('Ticket Number')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Otomatis dibuat saat tiket disimpan')
                            ->helperText('Nomor tiket dibuat otomatis dan unik.'),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(TicketStatus::options())
                            ->default(TicketStatus::Open->value)
                            ->required()
                            ->live()
                            ->visible(fn (): bool => auth()->user()?->isStaff() ?? false),
                        Forms\Components\TextInput::make('subject')
                            ->label('Subject')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ringkasan permasalahan…')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->required()
                            ->rows(6)
                            ->maxLength(10000)
                            ->placeholder('Jelaskan permasalahan secara detail…')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('category_id')
                            ->label('Category')
                            ->options(fn ($state): array => static::categoryOptions(is_numeric($state) ? (int) $state : null))
                            ->searchable()
                            ->required()
                            ->live(),
                        Forms\Components\Select::make('priority')
                            ->label('Priority')
                            ->options(TicketPriority::options())
                            ->default(TicketPriority::Medium->value)
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Assignment')
                    ->schema([
                        Forms\Components\Select::make('reporter_id')
                            ->label('Reporter')
                            ->options(static::reporterOptions())
                            ->default(fn (): int => auth()->id())
                            ->disabled(fn (): bool => ! auth()->user()?->isStaff())
                            ->dehydrated(true)
                            ->searchable()
                            ->preload()
                            ->helperText('Pelapor tiket.'),
                        Forms\Components\Select::make('assigned_to')
                            ->label('Assigned Staff')
                            ->options(static::staffOptions())
                            ->placeholder('Belum ditugaskan')
                            ->searchable()
                            ->preload()
                            ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
                            ->helperText('Kosongkan bila belum ada petugas yang menangani.'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Attachment')
                    ->schema([
                        Forms\Components\FileUpload::make('attachment')
                            ->label('File')
                            ->directory('tickets/attachments')
                            ->disk('public')
                            ->acceptedFileTypes([
                                'image/jpeg',
                                'image/png',
                                'image/gif',
                                'image/webp',
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'text/plain',
                                'application/zip',
                            ])
                            ->maxSize(5120)
                            ->helperText('Maksimal 5 MB. Format: gambar, PDF, Office, TXT, ZIP.')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(fn (Get $get, ?Ticket $record): bool => blank($record?->attachment)),
            ]);
    }

    /* ---------------------------------------------------------------
     | Table
     --------------------------------------------------------------- */

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->searchable(['subject', 'ticket_number'])
                    ->sortable()
                    ->wrap()
                    ->limit(60)
                    ->tooltip(fn (Ticket $record): string => $record->subject)
                    ->description(fn (Ticket $record): string => $record->ticket_number . ' · ' . ($record->category?->name ?? '-')),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (TicketStatus $state): string => $state->label())
                    ->color(fn (TicketStatus $state): string => $state->color())
                    ->sortable(),
                Tables\Columns\TextColumn::make('priority')
                    ->label('Priority')
                    ->badge()
                    ->formatStateUsing(fn (TicketPriority $state): string => $state->label())
                    ->color(fn (TicketPriority $state): string => $state->color())
                    ->sortable()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('assignee.name')
                    ->label('Assignee')
                    ->placeholder('Unassigned')
                    ->badge()
                    ->color('warning')
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('reporter.name')
                    ->label('Reporter')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(TicketStatus::options())
                    ->multiple(),
                Tables\Filters\SelectFilter::make('priority')
                    ->label('Priority')
                    ->options(TicketPriority::options())
                    ->multiple(),
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),
                Tables\Filters\SelectFilter::make('assigned_to')
                    ->label('Assignee')
                    ->relationship('assignee', 'name'),
                Tables\Filters\Filter::make('created_at')
                    ->label('Date Range')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From'),
                        Forms\Components\DatePicker::make('to')->label('To'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $from) => $q->whereDate('created_at', '>=', $from))
                            ->when($data['to'] ?? null, fn (Builder $q, $to) => $q->whereDate('created_at', '<=', $to));
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('assign')
                        ->label('Assign')
                        ->icon('heroicon-m-user-plus')
                        ->color('warning')
                        ->form([
                            Forms\Components\Select::make('assigned_to')
                                ->label('Assigned Staff')
                                ->options(static::staffOptions())
                                ->searchable()
                                ->preload()
                                ->required(),
                        ])
                        ->action(function (Ticket $record, array $data): void {
                            $record->update(['assigned_to' => $data['assigned_to']]);

                            \Filament\Notifications\Notification::make()
                                ->title('Tiket ditugaskan')
                                ->body($record->ticket_number . ' → ' . $record->assignee?->name)
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Ticket $record): bool => (auth()->user()?->isAdmin() ?? false)
                            && \Illuminate\Support\Facades\Gate::check('update', $record)),
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->tooltip('Actions'),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    /* ---------------------------------------------------------------
     | Infolist (halaman detail tiket)
     --------------------------------------------------------------- */

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Ticket Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('ticket_number')
                            ->label('Ticket #')
                            ->badge()
                            ->color('gray'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (TicketStatus $state): string => $state->label())
                            ->color(fn (TicketStatus $state): string => $state->color()),
                        Infolists\Components\TextEntry::make('priority')
                            ->label('Priority')
                            ->badge()
                            ->formatStateUsing(fn (TicketPriority $state): string => $state->label())
                            ->color(fn (TicketPriority $state): string => $state->color()),
                        Infolists\Components\TextEntry::make('category.name')
                            ->label('Category')
                            ->badge()
                            ->color('gray'),
                        Infolists\Components\TextEntry::make('subject')
                            ->label('Subject')
                            ->weight('semibold')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('description')
                            ->label('Description')
                            ->markdown()
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('attachment')
                            ->label('Attachment')
                            ->url(fn (Ticket $record): ?string => $record->attachment
                                ? \Illuminate\Support\Facades\Storage::disk('public')->url($record->attachment)
                                : null)
                            ->openUrlInNewTab()
                            ->visible(fn (Ticket $record): bool => filled($record->attachment))
                            ->placeholder('Tidak ada lampiran')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('People & Timeline')
                    ->schema([
                        Infolists\Components\TextEntry::make('reporter.name')
                            ->label('Reporter')
                            ->icon('heroicon-m-user'),
                        Infolists\Components\TextEntry::make('assignee.name')
                            ->label('Assignee')
                            ->icon('heroicon-m-user-group')
                            ->placeholder('Belum ditugaskan'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime('d M Y H:i'),
                        Infolists\Components\TextEntry::make('resolved_at')
                            ->label('Resolved At')
                            ->dateTime('d M Y H:i')
                            ->placeholder('Belum diselesaikan'),
                    ])
                    ->columns(2),
            ]);
    }

    /* ---------------------------------------------------------------
     | Options untuk form
     --------------------------------------------------------------- */

    /**
     * Hanya kategori aktif; kategori lama pada tiket tetap ditampilkan.
     *
     * @return array<int, string>
     */
    public static function categoryOptions(?int $currentId = null): array
    {
        $options = TicketCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        if ($currentId && ! array_key_exists($currentId, $options)) {
            $category = TicketCategory::find($currentId);

            if ($category) {
                $options[$category->id] = $category->name;
            }
        }

        return $options;
    }

    /**
     * Pelapor: seluruh user, kecuali role user biasa hanya memilih dirinya sendiri.
     *
     * @return array<int, string>
     */
    public static function reporterOptions(): array
    {
        if (! auth()->user()?->isStaff()) {
            return [auth()->id() => auth()->user()->name];
        }

        return User::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * Petugas: user dengan role admin / staff.
     *
     * @return array<int, string>
     */
    public static function staffOptions(): array
    {
        return User::query()
            ->whereHas('roles', fn (Builder $query): Builder => $query->whereIn('name', [
                User::ROLE_ADMIN,
                User::ROLE_STAFF,
            ]))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /* ---------------------------------------------------------------
     | Halaman
     --------------------------------------------------------------- */

    public static function getPages(): array
    {
        return [
            'my' => Pages\ListMyTickets::route('/my'),
            'assigned' => Pages\ListAssignedTickets::route('/assigned'),
            'index' => Pages\ListTickets::route('/'),
            'create' => Pages\CreateTicket::route('/create'),
            'view' => Pages\ViewTicket::route('/{record}'),
            'edit' => Pages\EditTicket::route('/{record}/edit'),
        ];
    }

    /**
     * Percakapan & riwayat tiket ditampilkan di halaman detail.
     *
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        return [
            RelationManagers\CommentsRelationManager::class,
            RelationManagers\HistoryRelationManager::class,
        ];
    }
}
