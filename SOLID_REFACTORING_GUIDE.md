# SOLID Refactoring Guide - Implementation Examples

## DocuCast Application

This guide provides concrete code examples to fix the top 3 critical SOLID violations.

---

## #1: Extract Document Status Logic (SRP + OCP + DIP)

### Current Problem

The `Document` model mixes entity representation with complex status management logic.

### Refactoring Steps

#### Step 1: Create Document Status Enum

```php
// app/Enums/DocumentStatus.php

<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Pending = 'pending';
    case InReview = 'in_review';
    case Approved = 'approved';
    case RequiresRevision = 'revision';

    public function displayName(): string
    {
        return match ($this) {
            self::Pending => 'Pending Review',
            self::InReview => 'In Review',
            self::Approved => 'Approved',
            self::RequiresRevision => 'Revision Required',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::InReview => 'info',
            self::Approved => 'success',
            self::RequiresRevision => 'danger',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Approved || $this === self::RequiresRevision;
    }
}
```

#### Step 2: Create Document Status Service

```php
// app/Services/DocumentStatusService.php

<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Models\Document;
use Illuminate\Database\Query\Builder;

class DocumentStatusService
{
    /**
     * Calculate the next status based on reviews
     */
    public function calculateNextStatus(Document $document): DocumentStatus
    {
        $summary = $this->getReviewSummary($document);

        if ($summary['totalRecipients'] === 0) {
            return DocumentStatus::Pending;
        }

        $hasRevision = (bool) $summary['hasRevision'];
        $allApproved = $summary['approvedCount'] === $summary['totalRecipients'];

        if ($hasRevision || !$allApproved) {
            return DocumentStatus::InReview;
        }

        return DocumentStatus::Approved;
    }

    /**
     * Update document status based on reviews
     */
    public function updateStatus(Document $document): void
    {
        $nextStatus = $this->calculateNextStatus($document);

        if ($document->status !== $nextStatus->value) {
            $document->update(['status' => $nextStatus->value]);
        }
    }

    /**
     * Get aggregated review summary
     */
    private function getReviewSummary(Document $document): array
    {
        return \DB::table('document_recipients')
            ->leftJoin('document_reviews', function ($join): void {
                $join->on('document_reviews.document_id', '=', 'document_recipients.document_id')
                    ->on('document_reviews.user_id', '=', 'document_recipients.user_id');
            })
            ->where('document_recipients.document_id', $document->id)
            ->selectRaw('COUNT(DISTINCT document_recipients.user_id) AS total_recipients')
            ->selectRaw("COUNT(DISTINCT CASE WHEN document_reviews.status = 'approved' THEN document_reviews.user_id END) AS approved_recipients")
            ->selectRaw("MAX(CASE WHEN document_reviews.status = 'revision' THEN 1 ELSE 0 END) AS has_revision")
            ->first()
            ->then(fn($result) => [
                'totalRecipients' => (int) ($result->total_recipients ?? 0),
                'approvedCount' => (int) ($result->approved_recipients ?? 0),
                'hasRevision' => (bool) ($result->has_revision ?? false),
            ]) ?? [
                'totalRecipients' => 0,
                'approvedCount' => 0,
                'hasRevision' => false,
            ];
    }
}
```

#### Step 3: Create Document Review Authorization Service

```php
// app/Services/DocumentReviewAuthorizationService.php

<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;

class DocumentReviewAuthorizationService
{
    /**
     * Check if user can submit a review for this document
     */
    public function canUserSubmitReview(Document $document, User $user): bool
    {
        if (!$this->isRecipient($document, $user)) {
            return false;
        }

        return !$this->hasApprovedReview($document, $user);
    }

    /**
     * Allow a recipient to review again
     */
    public function allowReviewAgain(Document $document, int $recipientId): void
    {
        $document->reviews()
            ->where('user_id', $recipientId)
            ->where('status', 'approved')
            ->delete();

        app(DocumentStatusService::class)->updateStatus($document);
    }

    /**
     * Check if user is a recipient
     */
    private function isRecipient(Document $document, User $user): bool
    {
        return $document->recipients()
            ->where('users.id', $user->id)
            ->exists();
    }

    /**
     * Check if user has submitted an approved review
     */
    private function hasApprovedReview(Document $document, User $user): bool
    {
        return $document->reviews()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->exists();
    }
}
```

#### Step 4: Update Document Model

```php
// app/Models/Document.php - UPDATED

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'file_path',
        'file_name',
        'unique_code',
        'uploader_id',
        'status',
    ];

    protected static function booted(): void
    {
        static::created(function (self $document): void {
            if (filled($document->unique_code)) {
                return;
            }

            $datePart = $document->created_at?->format('Ymd') ?? now()->format('Ymd');

            $document->forceFill([
                'unique_code' => self::formatUniqueCode($document->uploader_id, $datePart, $document->id),
            ])->saveQuietly();
        });
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'document_recipients');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(DocumentReview::class);
    }

    /**
     * REMOVED: updateStatusBasedOnReviews() - Now in DocumentStatusService
     * REMOVED: canRecipientSubmitReview() - Now in DocumentReviewAuthorizationService
     * REMOVED: allowRecipientToReviewAgain() - Now in DocumentReviewAuthorizationService
     */

    public static function formatUniqueCode(int $uploaderId, string $datePart, int $documentId): string
    {
        return sprintf('#%d%s%06d', $uploaderId, $datePart, $documentId);
    }
}
```

---

## #2: Extract Document Controller Logic (SRP + DIP)

### Current Problem

The `DocumentController` handles request validation, file storage, DB transactions, and business logic.

### Refactoring Steps

#### Step 1: Create Document Upload Service

```php
// app/Services/DocumentUploadService.php

<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\RevisionHistory;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentUploadService
{
    public function __construct(
        private DocumentStatusService $statusService,
    ) {}

    /**
     * Store initial document upload
     */
    public function storeInitialUpload(
        UploadedFile $file,
        string $title,
        string $description,
        User $uploader,
    ): Document {
        return DB::transaction(function () use ($file, $title, $description, $uploader) {
            // 1. Store file
            $filePath = $this->storeFile($file);

            // 2. Create document
            $document = Document::create([
                'title' => $title,
                'description' => $description,
                'file_path' => $filePath,
                'file_name' => $file->getClientOriginalName(),
                'uploader_id' => $uploader->id,
                'status' => 'pending',
            ]);

            // 3. Create version
            DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => 1,
                'file_storage_path' => $filePath,
                'original_filename' => $file->getClientOriginalName(),
                'uploaded_by' => $uploader->id,
            ]);

            // 4. Record history
            RevisionHistory::create([
                'document_id' => $document->id,
                'related_version_id' => $document->versions()->first()->id,
                'commenter_id' => $uploader->id,
                'action_type' => 'SUBMITTED',
                'comments' => 'Initial document upload.',
            ]);

            return $document;
        });
    }

    /**
     * Store revised document upload
     */
    public function storeRevisionUpload(
        Document $document,
        UploadedFile $file,
        User $uploader,
    ): DocumentVersion {
        return DB::transaction(function () use ($document, $file, $uploader) {
            // 1. Verify document needs revision
            if ($document->status !== 'revision') {
                throw new \InvalidArgumentException('Document does not require revision');
            }

            // 2. Store file
            $filePath = $this->storeFile($file);

            // 3. Get next version number
            $nextVersionNumber = $document->versions()->max('version_number') + 1;

            // 4. Create new version
            $version = DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => $nextVersionNumber,
                'file_storage_path' => $filePath,
                'original_filename' => $file->getClientOriginalName(),
                'uploaded_by' => $uploader->id,
            ]);

            // 5. Update document status
            $document->update(['status' => 'in_review']);

            // 6. Record history
            RevisionHistory::create([
                'document_id' => $document->id,
                'related_version_id' => $version->id,
                'commenter_id' => $uploader->id,
                'action_type' => 'RESUBMITTED',
                'comments' => "Uploaded revision v{$nextVersionNumber}",
            ]);

            return $version;
        });
    }

    /**
     * Store file physically
     */
    private function storeFile(UploadedFile $file): string
    {
        return $file->store('documents', config('filesystems.default'));
    }
}
```

#### Step 2: Create Document Review Service

```php
// app/Services/DocumentReviewService.php

<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\RevisionHistory;
use App\Models\User;
use App\Notifications\RecipientSubmittedReviewNotification;
use Illuminate\Support\Facades\DB;

class DocumentReviewService
{
    public function __construct(
        private DocumentStatusService $statusService,
        private DocumentReviewAuthorizationService $authorizationService,
    ) {}

    /**
     * Process a document review (approve or request revision)
     */
    public function submitReview(
        Document $document,
        User $reviewer,
        string $decision,
        ?string $message = null,
    ): DocumentReview {
        return DB::transaction(function () use ($document, $reviewer, $decision, $message) {
            // Lock for consistency
            $document = Document::query()->lockForUpdate()->findOrFail($document->id);

            // Authorize
            abort_unless(
                $this->authorizationService->canUserSubmitReview($document, $reviewer),
                403
            );

            // Create or update review
            $review = DocumentReview::query()->updateOrCreate(
                ['document_id' => $document->id, 'user_id' => $reviewer->id],
                [
                    'status' => $decision,
                    'message' => $message,
                ],
            );

            // Update document status
            $this->statusService->updateStatus($document);

            // Record history
            RevisionHistory::create([
                'document_id' => $document->id,
                'related_version_id' => $document->versions()->latest()->first()->id,
                'commenter_id' => $reviewer->id,
                'action_type' => strtoupper($decision),
                'comments' => $message,
            ]);

            // Notify document uploader
            $this->notifyUploader($document, $review);

            return $review;
        });
    }

    /**
     * Send notification to uploader
     */
    private function notifyUploader(Document $document, DocumentReview $review): void
    {
        $uploader = $document->uploader;
        if ($uploader) {
            $uploader->notify(new RecipientSubmittedReviewNotification($document, $review));
        }
    }
}
```

#### Step 3: Create Document Form Request

```php
// app/Http/Requests/StoreDocumentRequest.php

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'document_file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'document_file.required' => 'Please upload a document.',
            'document_file.mimes' => 'Only PDF files are allowed.',
            'document_file.max' => 'File size must not exceed 10MB.',
        ];
    }
}
```

#### Step 4: Refactored Controller

```php
// app/Http/Controllers/DocumentController.php - REFACTORED

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentRequest;
use App\Models\Document;
use App\Services\DocumentUploadService;
use App\Services\DocumentReviewService;
use App\Services\DocumentReviewAuthorizationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class DocumentController extends Controller
{
    public function __construct(
        private DocumentUploadService $uploadService,
        private DocumentReviewService $reviewService,
        private DocumentReviewAuthorizationService $authorizationService,
    ) {}

    /**
     * Store initial upload
     */
    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        try {
            $document = $this->uploadService->storeInitialUpload(
                file: $request->file('document_file'),
                title: $request->string('title'),
                description: $request->string('description'),
                uploader: Auth::user(),
            );

            return redirect()->back()->with('success', 'Document uploaded successfully!');
        } catch (\Throwable $e) {
            return redirect()->back()
                ->with('error', 'Upload failed: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Submit review
     */
    public function submitReview(
        Document $document,
        \Illuminate\Http\Request $request,
    ): RedirectResponse {
        $validated = $request->validate([
            'decision' => ['required', 'in:approved,revision'],
            'message' => ['nullable', 'string', 'required_if:decision,revision'],
        ]);

        try {
            $this->reviewService->submitReview(
                document: $document,
                reviewer: Auth::user(),
                decision: $validated['decision'],
                message: $validated['message'] ?? null,
            );

            return redirect()->back()->with('success', 'Review submitted successfully!');
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            return redirect()->back()->with('error', 'Unauthorized to submit review.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Review submission failed: ' . $e->getMessage());
        }
    }

    /**
     * Upload revision
     */
    public function uploadRevision(
        Document $document,
        \Illuminate\Http\Request $request,
    ): RedirectResponse {
        $validated = $request->validate([
            'document_file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        try {
            $this->uploadService->storeRevisionUpload(
                document: $document,
                file: $validated['document_file'],
                uploader: Auth::user(),
            );

            return redirect()->back()->with('success', 'Revision uploaded successfully!');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }
}
```

---

## #3: Fix Hard-coded Status Strings (OCP)

### Current Problem

Status strings are hard-coded throughout the codebase.

### Refactoring Steps

#### Update DocumentsTable.php

```php
// app/Filament/Resources/Documents/Tables/DocumentsTable.php - UPDATED PARTS

<?php

use App\Enums\DocumentStatus;

// In columns array:
TextColumn::make('status')
    ->badge()
    ->color(fn(string $state): string =>
        DocumentStatus::tryFrom($state)?->badgeColor() ?? 'gray'
    )
    ->formatStateUsing(fn(string $state): string =>
        DocumentStatus::tryFrom($state)?->displayName() ?? $state
    )
    ->sortable(),

// In filters:
SelectFilter::make('status')
    ->options(
        collect(DocumentStatus::cases())->mapWithKeys(
            fn(DocumentStatus $status) => [$status->value => $status->displayName()]
        )->toArray()
    ),
```

#### Update View and Notification

```php
// app/Notifications/RecipientSubmittedReviewNotification.php - UPDATED PARTS

use App\Enums\DocumentStatus;

public function toMail(object $notifiable): MailMessage
{
    $reviewerName = $this->review->reviewer?->name ?? 'A reviewer';
    $status = DocumentStatus::tryFrom($this->review->status);

    $mail = (new MailMessage)
        ->subject("Document Review: {$this->document->title}")
        ->greeting("Hello {$notifiable->name},")
        ->line("{$reviewerName} has submitted a review for your document.")
        ->line("**Document:** {$this->document->title}")
        ->line("**Unique Code:** {$this->document->unique_code}")
        ->line("**Decision:** {$status?->displayName()}")
        // ...
}
```

---

## Testing Examples

Here's how testing becomes much easier after refactoring:

```php
// tests/Unit/Services/DocumentStatusServiceTest.php

<?php

namespace Tests\Unit\Services;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\User;
use App\Services\DocumentStatusService;
use PHPUnit\Framework\TestCase;
use Tests\TestCase as BaseTestCase;

class DocumentStatusServiceTest extends BaseTestCase
{
    private DocumentStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DocumentStatusService();
    }

    public function test_returns_pending_when_no_recipients(): void
    {
        $document = Document::factory()->create();

        $status = $this->service->calculateNextStatus($document);

        $this->assertEquals(DocumentStatus::Pending, $status);
    }

    public function test_returns_in_review_when_not_all_approved(): void
    {
        $document = Document::factory()->create();
        $recipients = User::factory()->count(3)->create();
        $document->recipients()->sync($recipients->pluck('id'));

        // Only 2 of 3 approved
        DocumentReview::factory()->count(2)->create([
            'document_id' => $document->id,
            'status' => 'approved',
        ]);

        $status = $this->service->calculateNextStatus($document);

        $this->assertEquals(DocumentStatus::InReview, $status);
    }

    public function test_returns_approved_when_all_approved(): void
    {
        $document = Document::factory()->create();
        $recipients = User::factory()->count(2)->create();
        $document->recipients()->sync($recipients->pluck('id'));

        DocumentReview::factory()->count(2)->create([
            'document_id' => $document->id,
            'status' => 'approved',
        ]);

        $status = $this->service->calculateNextStatus($document);

        $this->assertEquals(DocumentStatus::Approved, $status);
    }

    public function test_returns_in_review_when_has_revision_requested(): void
    {
        $document = Document::factory()->create();
        $recipients = User::factory()->count(2)->create();
        $document->recipients()->sync($recipients->pluck('id'));

        DocumentReview::factory()->create([
            'document_id' => $document->id,
            'status' => 'revision',
        ]);
        DocumentReview::factory()->create([
            'document_id' => $document->id,
            'status' => 'approved',
        ]);

        $status = $this->service->calculateNextStatus($document);

        $this->assertEquals(DocumentStatus::InReview, $status);
    }
}

// tests/Feature/DocumentReviewTest.php

<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\User;
use App\Services\DocumentReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipient_can_submit_review(): void
    {
        $uploader = User::factory()->create();
        $reviewer = User::factory()->create();

        $document = Document::factory()->create(['uploader_id' => $uploader->id]);
        $document->recipients()->sync([$reviewer->id]);

        $service = $this->app->make(DocumentReviewService::class);

        $result = $service->submitReview(
            document: $document,
            reviewer: $reviewer,
            decision: 'approved',
            message: null,
        );

        $this->assertInstanceOf(DocumentReview::class, $result);
        $this->assertEquals('approved', $result->status);
        $this->assertDatabaseHas('document_reviews', [
            'document_id' => $document->id,
            'user_id' => $reviewer->id,
            'status' => 'approved',
        ]);
    }

    public function test_non_recipient_cannot_submit_review(): void
    {
        $uploader = User::factory()->create();
        $reviewer = User::factory()->create(); // Not added as recipient

        $document = Document::factory()->create(['uploader_id' => $uploader->id]);

        $service = $this->app->make(DocumentReviewService::class);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $service->submitReview(
            document: $document,
            reviewer: $reviewer,
            decision: 'approved',
            message: null,
        );
    }
}
```

---

## Implementation Checklist

- [ ] Create `app/Enums/DocumentStatus.php`
- [ ] Create `app/Services/DocumentStatusService.php`
- [ ] Create `app/Services/DocumentReviewAuthorizationService.php`
- [ ] Create `app/Services/DocumentUploadService.php`
- [ ] Create `app/Services/DocumentReviewService.php`
- [ ] Create `app/Http/Requests/StoreDocumentRequest.php`
- [ ] Refactor `app/Models/Document.php`
- [ ] Refactor `app/Http/Controllers/DocumentController.php`
- [ ] Update `app/Filament/Resources/Documents/Tables/DocumentsTable.php`
- [ ] Update `app/Notifications/RecipientSubmittedReviewNotification.php`
- [ ] Create unit tests for services
- [ ] Create feature tests for workflows
- [ ] Run `php artisan test` to verify
- [ ] Run `vendor/bin/pint --dirty` to format code

---

## Expected Benefits After Refactoring

✅ **Better Testability**

- Services can be unit tested with mock dependencies
- No need for database transactions in tests

✅ **Code Reusability**

- Services can be used from controllers, commands, APIs
- Business logic independent of presentation layer

✅ **Easier Maintenance**

- Changes to one concern don't ripple through entire codebase
- Clear separation of concerns

✅ **Better Scalability**

- Easy to add new document workflows
- New status types can be added to enum

✅ **Improved Performance**

- Easier to optimize queries in isolation
- Can add caching to services
