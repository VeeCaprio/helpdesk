<?php

namespace App\Filament\Admin\Resources\TicketResource\Pages;

use App\Enums\TicketStatus;
use App\Filament\Admin\Resources\TicketResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    /**
     * Reporter hanya boleh membuat tiket untuk dirinya sendiri dan tidak
     * boleh menentukan status/penugasan awal.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! auth()->user()?->isStaff()) {
            $data['reporter_id'] = auth()->id();
            $data['assigned_to'] = null;
            $data['status'] = TicketStatus::Open->value;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    // Riwayat pembuatan dicatat oleh TicketObserver (satu sumber kebenaran).
}
