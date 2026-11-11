<?php

namespace App\Filament\Admin\Resources\TicketResource\RelationManagers;

use App\Models\Ticket;
use App\Models\TicketComment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Percakapan tiket (komentar pelapor & petugas) — kronologis dari atas ke bawah.
 */
class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Conversation';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Textarea::make('message')
                    ->label('Message')
                    ->required()
                    ->rows(4)
                    ->maxLength(5000)
                    ->placeholder('Tulis balasan atau informasi penanganan…')
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('attachment')
                    ->label('Attachment')
                    ->directory('ticket-comments/attachments')
                    ->disk('public')
                    ->acceptedFileTypes([
                        'image/jpeg',
                        'image/png',
                        'image/gif',
                        'image/webp',
                        'application/pdf',
                        'text/plain',
                        'application/zip',
                    ])
                    ->maxSize(5120)
                    ->helperText('Maksimal 5 MB.')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('message')
                    ->label('Message')
                    ->wrap()
                    ->limit(300)
                    ->searchable()
                    ->description(fn (TicketComment $record): string => collect([
                        $record->user?->name,
                        $record->user?->roles->pluck('name')->first(),
                        $record->created_at->diffForHumans(),
                    ])->filter()->implode(' · ')),
                Tables\Columns\TextColumn::make('attachment')
                    ->label('File')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? 'View' : '-')
                    ->url(fn (?string $state): ?string => filled($state) ? Storage::disk('public')->url($state) : null)
                    ->openUrlInNewTab()
                    ->placeholder('-'),
            ])
            ->defaultSort('created_at', 'asc')
            ->paginated([10, 25, 50])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Comment')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->modalHeading('Add Comment')
                    ->modalSubmitActionLabel('Post Comment')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->visible(fn (TicketComment $record): bool => $this->canDeleteComment($record)),
            ])
            ->bulkActions([]);
    }

    /**
     * Relation manager ini tetap interaktif pada halaman ViewTicket.
     * Filament 3 menonaktifkan aksi relation manager di halaman view secara
     * default; komentar adalah pengecualian yang memang harus bisa ditambah.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * Jangan tampilkan percakapan tiket yang tidak boleh dilihat pengguna.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Ticket
            && Gate::check('view', $ownerRecord)
            && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    /**
     * Hanya admin atau penulis komentar yang boleh menghapus komentarnya,
     * dan hanya bila tiketnya masih dapat ia lihat.
     */
    private function canDeleteComment(TicketComment $record): bool
    {
        $user = auth()->user();

        if (! $user || ! Gate::check('view', $this->getOwnerRecord())) {
            return false;
        }

        return $user->isAdmin() || (int) $record->user_id === (int) $user->id;
    }

    /**
     * Menambah komentar hanya untuk pihak yang boleh mengomentari tiket ini.
     */
    protected function canCreate(): bool
    {
        return Gate::check('comment', $this->getOwnerRecord());
    }

    /**
     * Aksi hapus memakai aturan komentar, bukan policy TicketComment yang
     * tidak memiliki policy tersendiri.
     */
    protected function canDelete(Model $record): bool
    {
        return $record instanceof TicketComment && $this->canDeleteComment($record);
    }
}
