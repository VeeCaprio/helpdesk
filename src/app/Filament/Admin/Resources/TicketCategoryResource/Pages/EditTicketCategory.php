<?php

namespace App\Filament\Admin\Resources\TicketCategoryResource\Pages;

use App\Filament\Admin\Resources\TicketCategoryResource;
use App\Models\TicketCategory;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTicketCategory extends EditRecord
{
    protected static string $resource = TicketCategoryResource::class;

    /**
     * Slug hanya dibuat ulang saat nama kategori benar-benar berubah.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['name'] ?? null) !== $this->record->name) {
            $data['slug'] = TicketCategory::makeSlug($data['name']);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->disabled(fn ($record): bool => $record->tickets()->exists())
                ->tooltip(fn ($record): ?string => $record->tickets()->exists()
                    ? 'Kategori masih dipakai oleh tiket — tidak bisa dihapus.'
                    : null),
        ];
    }
}
