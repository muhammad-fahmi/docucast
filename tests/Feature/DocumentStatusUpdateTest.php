<?php

use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
use App\Services\DocumentStatusService;
use App\Services\DocumentReviewAuthorizationService;

it('marks document approved only when all recipients approved', function (): void {
    $uploader = User::factory()->create();
    $recipientA = User::factory()->create();
    $recipientB = User::factory()->create();

    $document = Document::query()->create([
        'title' => 'SOP Draft',
        'description' => null,
        'file_path' => 'documents/sop-draft.pdf',
        'file_name' => 'sop-draft.pdf',
        'uploader_id' => $uploader->id,
        'status' => 'in_review',
    ]);

    $document->recipients()->sync([$recipientA->id, $recipientB->id]);

    DocumentReview::query()->upsert([
        [
            'document_id' => $document->id,
            'user_id' => $recipientA->id,
            'status' => 'approved',
            'message' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ], ['document_id', 'user_id'], ['status', 'message', 'updated_at']);

    app(DocumentStatusService::class)->updateStatus($document);

    expect($document->fresh()->status)->toBe('in_review');

    DocumentReview::query()->upsert([
        [
            'document_id' => $document->id,
            'user_id' => $recipientB->id,
            'status' => 'approved',
            'message' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ], ['document_id', 'user_id'], ['status', 'message', 'updated_at']);

    app(DocumentStatusService::class)->updateStatus($document);

    expect($document->fresh()->status)->toBe('approved');
});

it('marks document pending when recipients are removed', function (): void {
    $uploader = User::factory()->create();
    $recipient = User::factory()->create();

    $document = Document::query()->create([
        'title' => 'Work Instruction',
        'description' => null,
        'file_path' => 'documents/work-instruction.pdf',
        'file_name' => 'work-instruction.pdf',
        'uploader_id' => $uploader->id,
        'status' => 'in_review',
    ]);

    $document->recipients()->sync([$recipient->id]);
    $document->recipients()->sync([]);
    app(DocumentStatusService::class)->updateStatus($document);

    expect($document->fresh()->status)->toBe('pending');
});

it('generates a unique code on document upload', function (): void {
    $uploader = User::factory()->create();

    $firstDocument = Document::query()->create([
        'title' => 'First Upload',
        'description' => null,
        'file_path' => 'documents/first-upload.pdf',
        'file_name' => 'first-upload.pdf',
        'uploader_id' => $uploader->id,
        'status' => 'pending',
    ]);

    $secondDocument = Document::query()->create([
        'title' => 'Second Upload',
        'description' => null,
        'file_path' => 'documents/second-upload.pdf',
        'file_name' => 'second-upload.pdf',
        'uploader_id' => $uploader->id,
        'status' => 'pending',
    ]);

    $firstDocument = $firstDocument->fresh();
    $secondDocument = $secondDocument->fresh();

    $firstExpectedCode = Document::formatUniqueCode(
        $uploader->id,
        $firstDocument->created_at->format('Ymd'),
        $firstDocument->id,
    );

    expect($firstDocument->unique_code)
        ->toStartWith('#')
        ->toBe($firstExpectedCode);

    expect($secondDocument->unique_code)
        ->not->toBe($firstDocument->unique_code);
});

it('blocks recipient from reviewing again after approving', function (): void {
    $uploader = User::factory()->create();
    $recipient = User::factory()->create();

    $document = Document::query()->create([
        'title' => 'Policy Document',
        'description' => null,
        'file_path' => 'documents/policy-document.pdf',
        'file_name' => 'policy-document.pdf',
        'uploader_id' => $uploader->id,
        'status' => 'in_review',
    ]);

    $document->recipients()->sync([$recipient->id]);

    DocumentReview::query()->create([
        'document_id' => $document->id,
        'user_id' => $recipient->id,
        'status' => 'approved',
        'message' => null,
    ]);

    expect(app(DocumentReviewAuthorizationService::class)->canUserSubmitReview($document, $recipient))->toBeFalse();
});

it('allows recipient to review again after uploader reopens recipient review', function (): void {
    $uploader = User::factory()->create();
    $recipient = User::factory()->create();

    $document = Document::query()->create([
        'title' => 'QMS Procedure',
        'description' => null,
        'file_path' => 'documents/qms-procedure.pdf',
        'file_name' => 'qms-procedure.pdf',
        'uploader_id' => $uploader->id,
        'status' => 'approved',
    ]);

    $document->recipients()->sync([$recipient->id]);

    DocumentReview::query()->create([
        'document_id' => $document->id,
        'user_id' => $recipient->id,
        'status' => 'approved',
        'message' => null,
    ]);

    app(DocumentReviewAuthorizationService::class)->allowReviewAgain($document, $recipient->id);

    expect(app(DocumentReviewAuthorizationService::class)->canUserSubmitReview($document->fresh(), $recipient))->toBeTrue();
    expect(DocumentReview::query()->where('document_id', $document->id)->where('user_id', $recipient->id)->exists())->toBeFalse();
    expect($document->fresh()->status)->toBe('in_review');
});
