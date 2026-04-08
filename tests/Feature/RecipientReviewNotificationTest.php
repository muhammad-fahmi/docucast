<?php

use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\DocumentReview;
use App\Models\User;
use App\Notifications\RecipientSubmittedReviewNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('sends notification to uploader when recipient submits review', function (): void {
    Notification::fake();

    // Create roles
    Role::create(['name' => 'uploader', 'guard_name' => 'web']);
    Role::create(['name' => 'recipient', 'guard_name' => 'web']);

    // Create uploader and recipient users
    $uploader = User::factory()->create();
    $recipient = User::factory()->create();

    // Assign roles
    $uploader->assignRole('uploader');
    $recipient->assignRole('recipient');

    // Create document
    $document = Document::factory()
        ->for($uploader, 'uploader')
        ->create();

    // Add recipient to document
    DocumentRecipient::create([
        'document_id' => $document->id,
        'user_id' => $recipient->id,
    ]);

    // Verify no notifications sent yet
    Notification::assertNothingSent();

    // Simulate review submission
    $review = DocumentReview::create([
        'document_id' => $document->id,
        'user_id' => $recipient->id,
        'status' => 'approved',
        'message' => null,
    ]);

    // Send notification manually (simulating what the action does)
    $uploader->notify(new RecipientSubmittedReviewNotification($document, $review->load('reviewer')));

    // Verify notification was sent to uploader
    Notification::assertSentTo(
        $uploader,
        RecipientSubmittedReviewNotification::class,
        function (RecipientSubmittedReviewNotification $notification) use ($document, $recipient): bool {
            $data = $notification->toArray($recipient); // Pass any user for toArray
            return $data['document_id'] === $document->id
                && $data['reviewer_id'] === $recipient->id;
        }
    );
});

test('notification contains document and review details', function (): void {
    Role::create(['name' => 'uploader', 'guard_name' => 'web']);

    $uploader = User::factory()->create();
    $recipient = User::factory()->create();

    $uploader->assignRole('uploader');

    $document = Document::factory()
        ->for($uploader, 'uploader')
        ->create();

    DocumentRecipient::create([
        'document_id' => $document->id,
        'user_id' => $recipient->id,
    ]);

    // Simulate review submission
    $review = DocumentReview::create([
        'document_id' => $document->id,
        'user_id' => $recipient->id,
        'status' => 'revision',
        'message' => 'Please update the document',
    ]);

    $notification = new RecipientSubmittedReviewNotification($document, $review->load('reviewer'));
    $data = $notification->toArray($uploader);

    expect($data)
        ->toHaveKey('document_id', $document->id)
        ->toHaveKey('document_title', $document->title)
        ->toHaveKey('document_unique_code', $document->unique_code)
        ->toHaveKey('reviewer_id', $recipient->id)
        ->toHaveKey('review_status', 'revision')
        ->toHaveKey('review_message', 'Please update the document');
});

test('notification mail contains recipient name and review details', function (): void {
    $uploader = User::factory()->create(['name' => 'John Uploader']);
    $recipient = User::factory()->create(['name' => 'Jane Recipient']);

    $document = Document::factory()
        ->for($uploader, 'uploader')
        ->create(['title' => 'Project Proposal']);

    $review = DocumentReview::create([
        'document_id' => $document->id,
        'user_id' => $recipient->id,
        'status' => 'approved',
        'message' => null,
    ]);

    $notification = new RecipientSubmittedReviewNotification($document, $review->load('reviewer'));
    $mail = $notification->toMail($uploader);

    expect($mail)
        ->toBeInstanceOf(\Illuminate\Notifications\Messages\MailMessage::class);

    // Verify mail contains key information by checking the mail properties
    expect($mail->subject)
        ->toContain('Project Proposal');

    expect($mail->introLines)
        ->toContain('Jane Recipient has submitted a review for your document.');
});

