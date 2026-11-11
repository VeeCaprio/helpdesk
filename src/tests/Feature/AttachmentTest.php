<?php

use App\Filament\Admin\Resources\TicketResource\Pages\CreateTicket;
use App\Filament\Admin\Resources\TicketResource\Pages\ViewTicket;
use App\Filament\Admin\Resources\TicketResource\RelationManagers\CommentsRelationManager;
use App\Models\Ticket;
use App\Models\TicketCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Semua tipe yang diizinkan pada form tiket.
 *
 * @return array<string, array{string, string}>
 */
function allowedTicketAttachments(): array
{
    return [
        'JPEG' => ['photo.jpg', 'image/jpeg'],
        'PNG' => ['photo.png', 'image/png'],
        'GIF' => ['photo.gif', 'image/gif'],
        'WebP' => ['photo.webp', 'image/webp'],
        'PDF' => ['document.pdf', 'application/pdf'],
        'Word' => ['document.doc', 'application/msword'],
        'Word (docx)' => ['document.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'Excel' => ['sheet.xls', 'application/vnd.ms-excel'],
        'Excel (xlsx)' => ['sheet.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'Text' => ['notes.txt', 'text/plain'],
        'ZIP' => ['archive.zip', 'application/zip'],
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function ticketFormData(array $overrides = []): array
{
    return [
        'subject' => 'Tiket dengan lampiran',
        'description' => 'Mohon periksa lampiran berikut.',
        'category_id' => TicketCategory::factory()->create()->id,
        'priority' => 'medium',
        ...$overrides,
    ];
}

it('accepts every allowed ticket attachment type', function () {
    Storage::fake('public');

    $admin = makeUserWithRole('admin');

    foreach (allowedTicketAttachments() as $label => [$name, $mime]) {
        Livewire::actingAs($admin)
            ->test(CreateTicket::class)
            ->fillForm(ticketFormData([
                'subject' => "Lampiran {$label}",
                'attachment' => UploadedFile::fake()->create($name, 100, $mime),
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Ticket::where('subject', "Lampiran {$label}")->exists())
            ->toBeTrue("Tipe lampiran {$label} seharusnya diterima.");
    }
});

it('rejects a ticket attachment with a disallowed type', function () {
    Storage::fake('public');

    $admin = makeUserWithRole('admin');

    Livewire::actingAs($admin)
        ->test(CreateTicket::class)
        ->fillForm(ticketFormData([
            'subject' => 'Lampiran berbahaya',
            'attachment' => UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload'),
        ]))
        ->call('create')
        ->assertHasFormErrors(['attachment']);

    expect(Ticket::where('subject', 'Lampiran berbahaya')->exists())->toBeFalse();
});

it('rejects a ticket attachment larger than five megabytes', function () {
    Storage::fake('public');

    $admin = makeUserWithRole('admin');

    Livewire::actingAs($admin)
        ->test(CreateTicket::class)
        ->fillForm(ticketFormData([
            'subject' => 'Lampiran kebesaran',
            'attachment' => UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf'),
        ]))
        ->call('create')
        ->assertHasFormErrors(['attachment']);

    expect(Ticket::where('subject', 'Lampiran kebesaran')->exists())->toBeFalse();
});

it('accepts a ticket attachment exactly at the five megabyte limit', function () {
    Storage::fake('public');

    $admin = makeUserWithRole('admin');

    Livewire::actingAs($admin)
        ->test(CreateTicket::class)
        ->fillForm(ticketFormData([
            'subject' => 'Lampiran tepat 5 MB',
            'attachment' => UploadedFile::fake()->create('limit.pdf', 5120, 'application/pdf'),
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Ticket::where('subject', 'Lampiran tepat 5 MB')->exists())->toBeTrue();
});

it('renders a conversation containing comments without attachments', function () {
    $reporter = makeUserWithRole('user');
    $ticket = Ticket::factory()->reportedBy($reporter)->create();
    $ticket->comments()->create([
        'user_id' => $reporter->id,
        'message' => 'Komentar tanpa lampiran.',
    ]);

    Livewire::actingAs($reporter)
        ->test(CommentsRelationManager::class, [
            'ownerRecord' => $ticket,
            'pageClass' => ViewTicket::class,
        ])
        ->assertSuccessful()
        ->assertSee('Komentar tanpa lampiran.');
});

it('accepts every allowed comment attachment type', function () {
    Storage::fake('public');

    $reporter = makeUserWithRole('user');
    $ticket = Ticket::factory()->reportedBy($reporter)->create();

    $allowed = [
        'JPEG' => ['photo.jpg', 'image/jpeg'],
        'PNG' => ['photo.png', 'image/png'],
        'GIF' => ['photo.gif', 'image/gif'],
        'WebP' => ['photo.webp', 'image/webp'],
        'PDF' => ['document.pdf', 'application/pdf'],
        'Text' => ['notes.txt', 'text/plain'],
        'ZIP' => ['archive.zip', 'application/zip'],
    ];

    foreach ($allowed as $label => [$name, $mime]) {
        Livewire::actingAs($reporter)
            ->test(CommentsRelationManager::class, [
                'ownerRecord' => $ticket,
                'pageClass' => ViewTicket::class,
            ])
            ->callTableAction('create', data: [
                'message' => "Lampiran {$label}",
                'attachment' => UploadedFile::fake()->create($name, 10, $mime),
            ])
            ->assertHasNoTableActionErrors();
    }

    expect($ticket->comments()->count())->toBe(count($allowed));
});

it('lets an authorized user add a comment with an allowed attachment', function () {
    Storage::fake('public');

    $reporter = makeUserWithRole('user');
    $ticket = Ticket::factory()->reportedBy($reporter)->create();

    Livewire::actingAs($reporter)
        ->test(CommentsRelationManager::class, [
            'ownerRecord' => $ticket,
            'pageClass' => ViewTicket::class,
        ])
        ->callTableAction('create', data: [
            'message' => 'Berikut log pendukung.',
            'attachment' => UploadedFile::fake()->create('log.txt', 10, 'text/plain'),
        ])
        ->assertHasNoTableActionErrors();

    $comment = $ticket->comments()->firstOrFail();

    expect($comment->user_id)->toBe($reporter->id)
        ->and($comment->message)->toBe('Berikut log pendukung.')
        ->and($comment->attachment)->not->toBeNull();
});

it('rejects a comment attachment with a disallowed type', function () {
    Storage::fake('public');

    $reporter = makeUserWithRole('user');
    $ticket = Ticket::factory()->reportedBy($reporter)->create();

    Livewire::actingAs($reporter)
        ->test(CommentsRelationManager::class, [
            'ownerRecord' => $ticket,
            'pageClass' => ViewTicket::class,
        ])
        ->callTableAction('create', data: [
            'message' => 'Lampiran berbahaya.',
            'attachment' => UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload'),
        ])
        ->assertHasTableActionErrors(['attachment']);

    expect($ticket->comments()->count())->toBe(0);
});

it('rejects a comment attachment larger than five megabytes', function () {
    Storage::fake('public');

    $reporter = makeUserWithRole('user');
    $ticket = Ticket::factory()->reportedBy($reporter)->create();

    Livewire::actingAs($reporter)
        ->test(CommentsRelationManager::class, [
            'ownerRecord' => $ticket,
            'pageClass' => ViewTicket::class,
        ])
        ->callTableAction('create', data: [
            'message' => 'Lampiran kebesaran.',
            'attachment' => UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf'),
        ])
        ->assertHasTableActionErrors(['attachment']);

    expect($ticket->comments()->count())->toBe(0);
});

it('forbids a staff member from commenting on an unassigned ticket', function () {
    Storage::fake('public');

    $staff = makeUserWithRole('staff');
    $ticket = Ticket::factory()->create();

    Livewire::actingAs($staff)
        ->test(CommentsRelationManager::class, [
            'ownerRecord' => $ticket,
            'pageClass' => ViewTicket::class,
        ])
        ->assertTableActionHidden('create');
});
