<?php

use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\User;
use App\Notifications\DocumentReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('sends reminder notifications to pending recipients 1 day before limit date', function (): void {
    Notification::fake();

    $uploader = User::factory()->create();
    $recipientA = User::factory()->create();
    $recipientB = User::factory()->create();

    $document = Document::query()->create([
        'title' => 'Important Policy',
        'description' => null,
        'file_path' => 'documents/policy.pdf',
        'file_name' => 'policy.pdf',
        'uploader_id' => $uploader->id,
        'status' => 'in_review',
        'auto_approve' => true,
        'limit_date' => Carbon::tomorrow()->toDateString(),
    ]);

    $document->recipients()->sync([$recipientA->id, $recipientB->id]);

    // Recipient A has already reviewed
    DocumentReview::query()->create([
        'document_id' => $document->id,
        'user_id' => $recipientA->id,
        'status' => 'approved',
        'message' => 'Looks good',
    ]);

    // Run the command
    Artisan::call('documents:process-limit-dates');

    // Assert recipient B got notified, but recipient A did not
    Notification::assertSentTo($recipientB, DocumentReminderNotification::class, function ($notification) use ($document) {
        return $notification->document->id === $document->id;
    });

    Notification::assertNotSentTo($recipientA, DocumentReminderNotification::class);
});

it('auto-approves pending recipients when limit date is reached or passed', function (): void {
    $uploader = User::factory()->create();
    $recipientA = User::factory()->create();
    $recipientB = User::factory()->create();

    $document = Document::query()->create([
        'title' => 'Critical Document',
        'description' => null,
        'file_path' => 'documents/critical.pdf',
        'file_name' => 'critical.pdf',
        'uploader_id' => $uploader->id,
        'status' => 'in_review',
        'auto_approve' => true,
        'limit_date' => Carbon::today()->toDateString(),
    ]);

    $document->recipients()->sync([$recipientA->id, $recipientB->id]);

    // Recipient A approved
    DocumentReview::query()->create([
        'document_id' => $document->id,
        'user_id' => $recipientA->id,
        'status' => 'approved',
        'message' => 'Approved manually',
    ]);

    // Recipient B has not reviewed. Status should be in_review before command
    expect($document->fresh()->status)->toBe('in_review');

    // Run the command
    Artisan::call('documents:process-limit-dates');

    // Recipient B should be auto-approved
    $reviewB = DocumentReview::query()
        ->where('document_id', $document->id)
        ->where('user_id', $recipientB->id)
        ->first();

    expect($reviewB)->not->toBeNull();
    expect($reviewB->status)->toBe('approved');
    expect($reviewB->message)->toBe('Auto-approved by system');

    // Overall document should now be approved
    expect($document->fresh()->status)->toBe('approved');
});

it('does not auto-approve documents if auto_approve is disabled', function (): void {
    $uploader = User::factory()->create();
    $recipient = User::factory()->create();

    $document = Document::query()->create([
        'title' => 'Manual Document',
        'description' => null,
        'file_path' => 'documents/manual.pdf',
        'file_name' => 'manual.pdf',
        'uploader_id' => $uploader->id,
        'status' => 'in_review',
        'auto_approve' => false,
        'limit_date' => Carbon::today()->toDateString(),
    ]);

    $document->recipients()->sync([$recipient->id]);

    // Run the command
    Artisan::call('documents:process-limit-dates');

    // Recipient should not be auto-approved
    $review = DocumentReview::query()
        ->where('document_id', $document->id)
        ->where('user_id', $recipient->id)
        ->first();

    expect($review)->toBeNull();
    expect($document->fresh()->status)->toBe('in_review');
});
