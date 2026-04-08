<?php

use App\Models\Division;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentRecipientResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('syncs unique recipients for individual selection', function (): void {
    Role::create(['name' => 'recipient', 'guard_name' => 'web']);

    $uploader = User::factory()->create();
    $recipientA = User::factory()->create();
    $recipientB = User::factory()->create();

    $document = Document::query()->create([
        'title' => 'Procedure Manual',
        'description' => null,
        'file_path' => 'documents/procedure-manual.pdf',
        'file_name' => 'procedure-manual.pdf',
        'uploader_id' => $uploader->id,
        'status' => 'pending',
    ]);

    $recipientIds = app(DocumentRecipientResolver::class)->syncRecipientsFromState($document, [
        'recipient_selection_type' => 'individual',
        'recipient_user_ids' => [$recipientA->id, $recipientA->id, $recipientB->id],
    ]);

    expect($recipientIds)->toBe([$recipientA->id, $recipientB->id]);
    expect($document->recipients()->pluck('users.id')->sort()->values()->all())
        ->toBe(collect([$recipientA->id, $recipientB->id])->sort()->values()->all());
});

it('syncs only recipient-role users for division selection', function (): void {
    Role::create(['name' => 'recipient', 'guard_name' => 'web']);

    $division = Division::query()->create([
        'name' => 'Quality Assurance',
        'slug' => 'quality-assurance',
    ]);

    $otherDivision = Division::query()->create([
        'name' => 'Operations',
        'slug' => 'operations',
    ]);

    $uploader = User::factory()->create();
    $recipientInDivision = User::factory()->create(['division_id' => $division->id]);
    $nonRecipientInDivision = User::factory()->create(['division_id' => $division->id]);
    $recipientOtherDivision = User::factory()->create(['division_id' => $otherDivision->id]);

    $recipientInDivision->assignRole('recipient');
    $recipientOtherDivision->assignRole('recipient');

    $document = Document::query()->create([
        'title' => 'Policy Memo',
        'description' => null,
        'file_path' => 'documents/policy-memo.pdf',
        'file_name' => 'policy-memo.pdf',
        'uploader_id' => $uploader->id,
        'status' => 'pending',
    ]);

    $recipientIds = app(DocumentRecipientResolver::class)->syncRecipientsFromState($document, [
        'recipient_selection_type' => 'division',
        'recipient_division_id' => $division->id,
    ]);

    expect($recipientIds)->toBe([$recipientInDivision->id]);
    expect($recipientIds)
        ->not->toContain($nonRecipientInDivision->id)
        ->not->toContain($recipientOtherDivision->id);
});

it('clears recipients when division selection is chosen without a division id', function (): void {
    Role::create(['name' => 'recipient', 'guard_name' => 'web']);

    $uploader = User::factory()->create();
    $recipient = User::factory()->create();
    $recipient->assignRole('recipient');

    $document = Document::query()->create([
        'title' => 'Checklist',
        'description' => null,
        'file_path' => 'documents/checklist.pdf',
        'file_name' => 'checklist.pdf',
        'uploader_id' => $uploader->id,
        'status' => 'in_review',
    ]);

    $document->recipients()->sync([$recipient->id]);

    $recipientIds = app(DocumentRecipientResolver::class)->syncRecipientsFromState($document, [
        'recipient_selection_type' => 'division',
        'recipient_division_id' => null,
    ]);

    expect($recipientIds)->toBe([]);
    expect($document->recipients()->count())->toBe(0);
});
