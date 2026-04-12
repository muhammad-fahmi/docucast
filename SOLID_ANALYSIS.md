# SOLID Principles Analysis Report

## DocuCast Application

**Analysis Date:** April 12, 2026  
**Total Critical Violations Found:** 14  
**Priority Level:** High Impact on Code Maintainability

---

## Executive Summary

The codebase shows a **moderate level of SOLID violations**, primarily concentrated in business logic placement, direct facade usage, and hard-coded status logic. The most critical issues are in the `Document` model (SRP violations), `DocumentController` (SRP & DIP), and status management logic (OCP violations).

---

## Detailed Violations (Ranked by Severity)

### 1. **Single Responsibility Principle (SRP) - Document Model**

**File:** [app/Models/Document.php](app/Models/Document.php)  
**Severity:** 🔴 **HIGH**  
**Type:** SRP Violation

**Issue:**
The `Document` model violates SRP by combining:

- Entity representation with attributes and relationships
- Status management logic (`updateStatusBasedOnReviews()`)
- Review authorization (`canRecipientSubmitReview()`)
- Permission management (`allowRecipientToReviewAgain()`)
- Unique code generation (`formatUniqueCode()`)
- Complex SQL queries for status calculation

**Code Example:**

```php
// Lines 57-75: Status calculation with complex joins
public function updateStatusBasedOnReviews(): void
{
    $summary = DB::table('document_recipients')
        ->leftJoin('document_reviews', function ($join): void { ... })
        ->selectRaw('COUNT(DISTINCT document_recipients.user_id) AS total_recipients')
        // Complex logic that mixes concerns
}

// Lines 82-100: Authorization logic
public function canRecipientSubmitReview(User $user): bool { ... }
```

**Impact:**

- Hard to test in isolation
- Difficult to reuse status logic in different contexts
- Changes to review workflow require modifying the model
- Violates the principle that a model should represent an entity, not orchestrate business logic

**Suggested Fix:**
Extract to dedicated service classes:

```php
// Create: app/Services/DocumentStatusService.php
class DocumentStatusService {
    public function updateStatusBasedOnReviews(Document $document): void { ... }
    public function calculateNextStatus(Document $document): string { ... }
}

// Create: app/Services/DocumentReviewAuthorizationService.php
class DocumentReviewAuthorizationService {
    public function canUserSubmitReview(Document $document, User $user): bool { ... }
    public function allowReviewAgain(Document $document, int $recipientId): void { ... }
}
```

---

### 2. **Dependency Inversion Principle (DIP) - Document Model Direct DB Usage**

**File:** [app/Models/Document.php](app/Models/Document.php)  
**Severity:** 🔴 **HIGH**  
**Type:** DIP Violation

**Issue:**
The `Document` model directly depends on the `DB` facade instead of injecting dependencies or using query builders:

**Code Example (Lines 59-75):**

```php
public function updateStatusBasedOnReviews(): void
{
    $summary = DB::table('document_recipients')  // Direct DB facade
        ->leftJoin('document_reviews', function ($join): void { ... })
        ->where('document_recipients.document_id', $this->id)
        // ...
}

// Lines 86-95
public function canRecipientSubmitReview(User $user): bool
{
    $isRecipient = DB::table('document_recipients')  // Direct DB facade
        ->where('document_id', $this->id)
        ->where('user_id', $user->id)
        ->exists();
}
```

**Impact:**

- Cannot mock database interactions in tests
- Tight coupling to Laravel's DB facade
- Cannot swap database implementations
- Harder to trace data flow

**Suggested Fix:**
Use relationships and query builders:

```php
public function canRecipientSubmitReview(User $user): bool
{
    // Use relationship method instead
    $isRecipient = $this->recipients()->where('users.id', $user->id)->exists();

    if (!$isRecipient) {
        return false;
    }

    return !$this->reviews()
        ->where('user_id', $user->id)
        ->where('status', 'approved')
        ->exists();
}
```

---

### 3. **Single Responsibility Principle (SRP) - DocumentController**

**File:** [app/Http/Controllers/DocumentController.php](app/Http/Controllers/DocumentController.php)  
**Severity:** 🔴 **HIGH**  
**Type:** SRP Violation

**Issue:**
The controller handles multiple responsibilities:

- Request validation
- File storage management
- Database transactions
- Complex business logic orchestration
- Status state machine logic
- History tracking

**Code Example (Lines 17-69):**

```php
public function store(Request $request)
{
    $request->validate([...]);  // 1. Validation

    try {
        DB::transaction(function () use ($request) {  // 2. Transaction management
            $user = Auth::user();

            // 3. File storage
            $path = $request->file('document_file')->store('documents', 'public');

            // 4. Document creation
            $document = Document::create([...]);

            // 5. Version creation
            $version = DocumentVersion::create([...]);

            // 6. Approval assignment
            DocumentApproval::create([...]);

            // 7. History recording
            RevisionHistory::create([...]);
        });
    } catch (\Exception $e) {
        // Generic error handling
    }
}
```

**Impact:**

- Controller is difficult to test
- Business logic is tied to HTTP layer
- Cannot reuse workflow from API or commands
- Transaction handling mixed with presentation logic

**Suggested Fix:**
Create action classes and services:

```php
// Create: app/Actions/CreateDocumentAction.php
class CreateDocumentAction {
    public function __construct(
        private DocumentValidator $validator,
        private FileStorageService $storage,
        private DocumentStatusService $statusService,
    ) {}

    public function execute(array $data): Document { ... }
}

// Then in controller:
public function store(Request $request, CreateDocumentAction $action)
{
    return $action->execute($request->validated());
}
```

---

### 4. **Open/Closed Principle (OCP) - Hard-coded Status Logic**

**File:** [app/Models/Document.php](app/Models/Document.php)  
**Severity:** 🔴 **HIGH**  
**Type:** OCP Violation

**Issue:**
Status logic uses hard-coded string values that are fragile and not extensible:

**Code Example (Lines 71-78):**

```php
$nextStatus = 'pending';  // Hard-coded string

if ($totalRecipients > 0) {
    $approvedCount = (int) ($summary?->approved_recipients ?? 0);
    $hasRevision = (int) ($summary?->has_revision ?? 0) === 1;

    $nextStatus = ($hasRevision || $approvedCount < $totalRecipients)
        ? 'in_review'      // Hard-coded string
        : 'approved';      // Hard-coded string
}
```

Also in [app/Filament/Resources/Documents/Tables/DocumentsTable.php](app/Filament/Resources/Documents/Tables/DocumentsTable.php) (Lines 65-70):

```php
->color(fn(string $state): string => match ($state) {
    'pending' => 'warning',
    'in_review' => 'info',
    'approved' => 'success',
    default => 'gray',
})
```

**Impact:**

- Adding new statuses requires code changes in multiple files
- No way to define custom status workflows
- Status strings duplicated across codebase
- Cannot test different status transitions easily

**Suggested Fix:**
Create an Enum for statuses:

```php
// Create: app/Enums/DocumentStatus.php
enum DocumentStatus: string {
    case Pending = 'pending';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Revision = 'revision';

    public function displayName(): string
    {
        return match ($this) {
            self::Pending => 'Pending Review',
            self::InReview => 'In Review',
            self::Approved => 'Approved',
            self::Revision => 'Revision Required',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::InReview => 'info',
            self::Approved => 'success',
            self::Revision => 'danger',
        };
    }
}

// Create: app/Services/StatusTransitionService.php
class StatusTransitionService {
    public function calculateNextStatus(
        int $totalRecipients,
        int $approvedCount,
        bool $hasRevision
    ): DocumentStatus {
        if ($totalRecipients === 0) {
            return DocumentStatus::Pending;
        }

        return ($hasRevision || $approvedCount < $totalRecipients)
            ? DocumentStatus::InReview
            : DocumentStatus::Approved;
    }
}
```

Then use throughout:

```php
$nextStatus = $this->statusTransitionService->calculateNextStatus(...);
$document->update(['status' => $nextStatus->value]);
```

---

### 5. **Dependency Inversion Principle (DIP) - DocumentController Direct DB Usage**

**File:** [app/Http/Controllers/DocumentController.php](app/Http/Controllers/DocumentController.php)  
**Severity:** 🔴 **HIGH**  
**Type:** DIP Violation

**Issue:**
Direct use of `DB::transaction()` and direct model creation without abstraction:

**Code Examples:**

```php
// Line 27
DB::transaction(function () use ($request) {
    $user = Auth::user();
    // Direct file storage
    $path = $request->file('document_file')->store('documents', 'public');
    // Direct model creation
    $document = Document::create([...]);
})

// Line 92
DB::transaction(function () use ($request, $document, $approval, $latestVersion, $user) {
    if ($request->decision === 'approve') {  // Direct decision logic
        $approval->update(['status' => 'APPROVED', 'processed_at' => now()]);
    } else {
        $approval->update(['status' => 'REJECTED_FOR_REVISION', 'processed_at' => now()]);
    }
})
```

**Impact:**

- Cannot test without a real database
- Tight coupling to model classes
- Hard to implement different storage strategies
- Authorization and business logic mixed

**Suggested Fix:**
Inject services:

```php
class DocumentController extends Controller
{
    public function __construct(
        private CreateDocumentService $createService,
        private ReviewDocumentService $reviewService,
        private DocumentAuthorizationService $authService,
    ) {}

    public function store(Request $request)
    {
        try {
            $document = $this->createService->execute($request->validated());
            return redirect()->back()->with('success', 'Document uploaded successfully!');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }

    public function review(Request $request, Document $document)
    {
        $user = Auth::user();

        if (!$this->authService->canReview($user, $document)) {
            abort(403);
        }

        $this->reviewService->processReview($document, $user, $request->validated());
        return redirect()->back()->with('success', 'Review submitted successfully!');
    }
}
```

---

### 6. **Dependency Inversion Principle (DIP) - DocumentsTable Complex Logic**

**File:** [app/Filament/Resources/Documents/Tables/DocumentsTable.php](app/Filament/Resources/Documents/Tables/DocumentsTable.php)  
**Severity:** 🟠 **MEDIUM**  
**Type:** DIP Violation

**Issue:**
The table configuration directly depends on Auth facade and contains complex business logic:

**Code Example (Lines 32-50):**

```php
$user = Auth::user();  // Direct facade dependency

return $table
    ->poll('5s')
    ->modifyQueryUsing(function (Builder $query) use ($user): Builder {
        $query->with(['uploader:id,name']);  // Query optimization
        $query->withExists([  // Complex query logic
            'reviews as has_approved_reviews' => fn(Builder $reviewQuery): Builder =>
                $reviewQuery->where('status', 'approved'),
        ]);

        if ($user?->hasRole('recipient')) {  // Authorization logic in table
            $query->withExists([
                'recipients as is_recipient' => fn(Builder $recipientQuery): Builder =>
                    $recipientQuery->where('users.id', $user->id),
                'reviews as has_approved_review_by_user' => fn(Builder $reviewQuery): Builder =>
                    $reviewQuery->where('user_id', $user->id)->where('status', 'approved'),
            ]);
        }

        return $query;
    })
```

**Also (Lines 139-154):** Complex review action with transaction logic:

```php
->action(function (array $data, $record) {
    // ... Authorization logic

    DB::transaction(function () use ($data, $record, $user, &$review): void {
        $document = Document::query()
            ->whereKey($record->id)
            ->lockForUpdate()
            ->firstOrFail();

        abort_unless($document->canRecipientSubmitReview($user), 403);

        // Upsert and notification logic all in table action
        DocumentReview::query()->upsert([...], [...], [...]);
        $document->updateStatusBasedOnReviews();

        // Fetch review for notification
        $review = DocumentReview::where('document_id', $document->id)
            ->where('user_id', $user->id)
            ->with('reviewer')
            ->first();
    }, 3);
})
```

**Impact:**

- Presentation logic tightly coupled to business logic
- Difficult to reuse review workflow
- Cannot test authorization logic independently
- Transaction and notification logic mixed with UI configuration

**Suggested Fix:**
Extract to service:

```php
// Create: app/Services/DocumentReviewService.php
class DocumentReviewService {
    public function __construct(
        private DocumentStatusService $statusService,
        private NotificationService $notificationService,
    ) {}

    public function submitReview(Document $document, User $reviewer, array $data): DocumentReview
    {
        return DB::transaction(function () use ($document, $reviewer, $data) {
            $document->lockForUpdate();

            if (!$document->canRecipientSubmitReview($reviewer)) {
                throw new UnauthorizedActionException('Cannot submit review');
            }

            $review = DocumentReview::query()->upsert(
                [[
                    'document_id' => $document->id,
                    'user_id' => $reviewer->id,
                    'status' => $data['status'],
                    'message' => $data['message'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]],
                ['document_id', 'user_id'],
                ['status', 'message', 'updated_at'],
            );

            $this->statusService->updateStatusBasedOnReviews($document);
            $this->notificationService->notifyUploader($document, $review);

            return $review;
        });
    }
}

// Then in table:
->action(function (array $data, $record) {
    $this->reviewService->submitReview($record, Auth::user(), $data);
})
```

---

### 7. **Single Responsibility Principle (SRP) - CreateDocument Page**

**File:** [app/Filament/Resources/Documents/Pages/CreateDocument.php](app/Filament/Resources/Documents/Pages/CreateDocument.php)  
**Severity:** 🟠 **MEDIUM**  
**Type:** SRP Violation

**Issue:**
The page handles multiple responsibilities:

- Form data transformation (mutateFormDataBeforeCreate)
- Recipient resolution (afterCreate)
- Notification creation (afterCreate)
- Status updates (afterCreate)
- Broadcast logic (afterCreate)

**Code Example (Lines 24-47):**

```php
protected function afterCreate(): void
{
    // 1. Resolve recipients from complex logic
    $state = $this->form->getRawState();
    $recipientIds = app(DocumentRecipientResolver::class)
        ->syncRecipientsFromState($this->record, $state);

    // 2. Update document status
    if (count($recipientIds) > 0) {
        $this->record->update(['status' => 'in_review']);

        // 3. Fetch recipients
        $recipients = User::query()
            ->whereIn('id', $recipientIds)
            ->get();

        if ($recipients->isNotEmpty()) {
            // 4. Create and broadcast notifications
            $dashboardNotification = FilamentNotification::make()
                ->title('New Document Assigned')
                ->body(sprintf('A new document "%s" (%s) has been assigned to you.',
                    $this->record->title,
                    $this->record->unique_code))
                // ...
                ->sendToDatabase($recipients);
            $dashboardNotification->broadcast($recipients);
        }
    }
}
```

**Impact:**

- Difficult to test page logic
- Notification creation tangled with form logic
- Cannot reuse notification pattern from API
- Hard to maintain notification templates

**Suggested Fix:**
Use registered listeners or events:

```php
// app/Events/DocumentCreatedEvent.php
class DocumentCreatedEvent {
    public function __construct(public Document $document, public array $recipientIds) {}
}

// app/Listeners/SendDocumentAssignmentNotification.php
class SendDocumentAssignmentNotification {
    public function handle(DocumentCreatedEvent $event): void
    {
        // All notification logic here
    }
}

// In CreateDocument:
protected function afterCreate(): void
{
    $state = $this->form->getRawState();
    $recipientIds = app(DocumentRecipientResolver::class)
        ->syncRecipientsFromState($this->record, $state);

    if (count($recipientIds) > 0) {
        $this->record->update(['status' => 'in_review']);
        event(new DocumentCreatedEvent($this->record, $recipientIds));
    }
}
```

---

### 8. **Open/Closed Principle (OCP) - Role-based Access Control Hard-coded**

**File:** [app/Http/Controllers/DocumentPreviewController.php](app/Http/Controllers/DocumentPreviewController.php)  
**Severity:** 🟠 **MEDIUM**  
**Type:** OCP Violation

**Issue:**
Authorization logic uses hard-coded role names and conditional checks:

**Code Example (Lines 20-23):**

```php
$isAllowed = $user->hasAnyRole(['super_admin', 'admin'])  // Hard-coded roles
    || $document->uploader_id === $user->id
    || $document->recipients()->where('users.id', $user->id)->exists();

abort_unless($isAllowed, 403);
```

**Impact:**

- Adding new access rules requires code changes
- Business logic mixed with controller
- Cannot extend authorization without modifying controller
- Not testable in isolation

**Suggested Fix:**
Use Laravel policies:

```php
// app/Policies/DocumentPolicy.php
public function view(User $user, Document $document): bool
{
    return $user->hasAnyRole(['super_admin', 'admin'])
        || $document->uploader_id === $user->id
        || $document->recipients()->where('users.id', $user->id)->exists();
}

// In controller:
public function __invoke(Document $document): BinaryFileResponse
{
    $this->authorize('view', $document);
    // ... return file
}
```

---

### 9. **Single Responsibility Principle (SRP) - DocumentsTable Action Logic**

**File:** [app/Filament/Resources/Documents/Tables/DocumentsTable.php](app/Filament/Resources/Documents/Tables/DocumentsTable.php)  
**Severity:** 🟠 **MEDIUM**  
**Type:** SRP Violation

**Issue:**
The "allow_re_review" action mixes multiple concerns:

**Code Example (Lines 216-240):**

```php
Action::make('allow_re_review')
    ->label('Allow Re-Review')
    // ...
    ->schema([
        Select::make('recipient_user_id')
            ->label('Recipient')
            ->options(function ($record): array {
                return $record->reviews()  // Data fetching
                    ->where('status', 'approved')
                    ->with('reviewer:id,name')
                    ->get()
                    ->mapWithKeys(...)
                    ->toArray();
            })
            ->required()
            ->searchable()
            ->preload(),
    ])
    ->action(function (array $data, $record): void {
        // Business logic in action
        $record->allowRecipientToReviewAgain($data['recipient_user_id']);
        // ... more logic
    })
```

**Impact:**

- Complex business logic in table action
- Cannot reuse from other contexts
- Difficult to test
- Presentation and business logic mixed

---

### 10. **Dependency Inversion Principle (DIP) - Service Locator Pattern**

**File:** [app/Filament/Resources/Documents/Pages/CreateDocument.php](app/Filament/Resources/Documents/Pages/CreateDocument.php)  
**Severity:** 🟠 **MEDIUM**  
**Type:** DIP Violation

**Issue:**
Using service locator pattern instead of constructor injection:

**Code Example (Line 26):**

```php
$recipientIds = app(DocumentRecipientResolver::class)  // Service locator
    ->syncRecipientsFromState($this->record, $state);
```

Also in [app/Filament/Resources/Documents/Tables/DocumentsTable.php](app/Filament/Resources/Documents/Tables/DocumentsTable.php):

```php
$this->reviewService->submitReview($record, Auth::user(), $data);
```

**Impact:**

- Hides dependencies - not obvious what services are used
- Makes testing harder - must mock the app container
- Violates explicit dependency declaration

**Suggested Fix:**
Use constructor injection in Livewire components and Resources:

```php
class CreateDocument extends CreateRecord
{
    public function __construct(
        private DocumentRecipientResolver $recipientResolver,
        private DocumentStatusService $statusService,
        private NotificationService $notificationService,
    ) {}

    protected function afterCreate(): void
    {
        $state = $this->form->getRawState();
        $recipientIds = $this->recipientResolver->syncRecipientsFromState(
            $this->record,
            $state
        );

        if (count($recipientIds) > 0) {
            $this->record->update(['status' => 'in_review']);
            $this->notificationService->notifyRecipients($this->record, $recipientIds);
        }
    }
}
```

---

### 11. **Interface Segregation Principle (ISP) - Overly Generic Policies**

**File:** [app/Policies/DocumentPolicy.php](app/Policies/DocumentPolicy.php) & [app/Policies/DivisionPolicy.php](app/Policies/DivisionPolicy.php)  
**Severity:** 🟡 **LOW**  
**Type:** ISP Violation

**Issue:**
All policies implement the same 11 methods regardless of actual needs:

**Code Example:**

```php
public function viewAny(AuthUser $authUser): bool { return $authUser->can('ViewAny:Document'); }
public function view(AuthUser $authUser, Document $document): bool { return $authUser->can('View:Document'); }
public function create(AuthUser $authUser): bool { return $authUser->can('Create:Document'); }
public function update(AuthUser $authUser, Document $document): bool { return $authUser->can('Update:Document'); }
public function delete(AuthUser $authUser, Document $document): bool { return $authUser->can('Delete:Document'); }
public function restore(AuthUser $authUser, Document $document): bool { return $authUser->can('Restore:Document'); }
public function forceDelete(AuthUser $authUser, Document $document): bool { return $authUser->can('ForceDelete:Document'); }
public function forceDeleteAny(AuthUser $authUser): bool { return $authUser->can('ForceDeleteAny:Document'); }
public function restoreAny(AuthUser $authUser): bool { return $authUser->can('RestoreAny:Document'); }
public function replicate(AuthUser $authUser, Document $document): bool { return $authUser->can('Replicate:Document'); }
public function reorder(AuthUser $authUser): bool { return $authUser->can('Reorder:Document'); }
```

**Impact:**

- Resources that don't need all operations still must implement them
- Code duplication across multiple policy files
- Tight coupling to specific permission strings

**Suggested Fix:**
Create base policy classes:

```php
// app/Policies/Contracts/BaseResourcePolicy.php
abstract class BaseResourcePolicy
{
    protected abstract function resourceName(): string;

    public function viewAny(User $user): bool
    {
        return $user->can("ViewAny:{$this->resourceName()}");
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can("View:{$this->resourceName()}");
    }
    // ... other standard methods
}

// app/Policies/DocumentPolicy.php
class DocumentPolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'Document';
    }
}
```

---

### 12. **Dependency Inversion Principle (DIP) - Direct Auth Facade Usage**

**File:** Multiple files  
**Severity:** 🟡 **LOW**  
**Type:** DIP Violation

**Issue:**
Direct usage of `Auth::user()` and `Auth::check()` throughout codebase:

**Files and Examples:**

- [app/Http/Controllers/DocumentController.php](app/Http/Controllers/DocumentController.php): Lines 24, 76, 117
- [app/Http/Controllers/DocumentPreviewController.php](app/Http/Controllers/DocumentPreviewController.php): Line 18
- [app/Filament/Resources/Documents/Tables/DocumentsTable.php](app/Filament/Resources/Documents/Tables/DocumentsTable.php): Line 33
- [app/Filament/Resources/Documents/Pages/CreateDocument.php](app/Filament/Resources/Documents/Pages/CreateDocument.php): Line 20
- [app/Livewire/Filament/DatabaseNotifications.php](app/Livewire/Filament/DatabaseNotifications.php)

```php
// Hard to test, hard to mock
$user = Auth::user();
if ($user->hasRole('recipient')) { ... }
```

**Impact:**

- Tight coupling to Laravel's Auth facade
- Tests must use real authentication or mock facades
- Cannot use different authentication providers without major refactoring

---

### 13. **Liskov Substitution Principle (LSP) - Inconsistent Model Relationships**

**File:** [app/Models/DocumentReview.php](app/Models/DocumentReview.php)  
**Severity:** 🟡 **LOW**  
**Type:** LSP Violation (Potential)

**Issue:**
The `reviewer()` relationship uses a different foreign key than typical:

**Code Example:**

```php
// Typical convention: user_id -> user (implicit)
public function reviewer(): BelongsTo
{
    return $this->belongsTo(User::class, 'user_id');  // Explicit foreign key
}
```

This breaks expectations since the field is named `user_id` but the relationship is `reviewer()`. This creates confusion when substituting models or changing relationship names.

**Suggested Fix:**
Either rename the field or provide both relationships:

```php
public function user(): BelongsTo
{
    return $this->belongsTo(User::class, 'user_id');
}

public function reviewer(): BelongsTo
{
    return $this->user();
}
```

---

### 14. **Open/Closed Principle (OCP) - Hardcoded File Validation**

**File:** [app/Filament/Resources/Documents/Schemas/DocumentForm.php](app/Filament/Resources/Documents/Schemas/DocumentForm.php)  
**Severity:** 🟡 **LOW**  
**Type:** OCP Violation

**Issue:**
File type validation is hard-coded in form schema:

**Code Example (Lines 28-35):**

```php
AdvancedFileUpload::make('file_path')
    ->label('Document File')
    ->required()
    ->directory('documents')
    ->visibility('private')
    ->storeFileNamesIn('file_name')
    ->pdfPreviewHeight(420)
    ->pdfToolbar(true)
    ->acceptedFileTypes([
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/*'
    ])
    ->maxSize(10240)
```

**Impact:**

- Adding new file types requires code changes
- No centralized file upload configuration
- Duplicated across multiple forms if needed

---

## Summary Table

| #   | Principle | Severity  | File                          | Issue                              | Effort to Fix |
| --- | --------- | --------- | ----------------------------- | ---------------------------------- | ------------- |
| 1   | SRP       | 🔴 HIGH   | Document.php                  | Multiple responsibilities in model | Medium        |
| 2   | DIP       | 🔴 HIGH   | Document.php                  | Direct DB facade usage             | Medium        |
| 3   | SRP       | 🔴 HIGH   | DocumentController.php        | Business logic in controller       | High          |
| 4   | OCP       | 🔴 HIGH   | Document.php                  | Hard-coded status strings          | Medium        |
| 5   | DIP       | 🔴 HIGH   | DocumentController.php        | Direct DB::transaction usage       | Medium        |
| 6   | DIP       | 🟠 MEDIUM | DocumentsTable.php            | Complex logic in table             | High          |
| 7   | SRP       | 🟠 MEDIUM | CreateDocument.php            | Multiple concerns in page          | Medium        |
| 8   | OCP       | 🟠 MEDIUM | DocumentPreviewController.php | Hard-coded roles                   | Low           |
| 9   | SRP       | 🟠 MEDIUM | DocumentsTable.php            | Action logic mixing                | Medium        |
| 10  | DIP       | 🟠 MEDIUM | CreateDocument.php            | Service locator pattern            | Low           |
| 11  | ISP       | 🟡 LOW    | Policies                      | Overly generic policies            | Low           |
| 12  | DIP       | 🟡 LOW    | Multiple files                | Direct Auth usage                  | Low           |
| 13  | LSP       | 🟡 LOW    | DocumentReview.php            | Inconsistent relationships         | Low           |
| 14  | OCP       | 🟡 LOW    | DocumentForm.php              | Hard-coded file types              | Low           |

---

## Recommendations by Priority

### Phase 1: Critical (Implement First)

1. **Extract DocumentStatusService** - Removes status logic from model
2. **Create Document Actions/Services** - Removes business logic from controller
3. **Create Service Interfaces** - Enables proper dependency injection
4. **Introduce Status Enum** - Fixes hard-coded strings

### Phase 2: Important (Implement Next)

5. **Extract Review Service** - Centralizes review workflow
6. **Move Notifications to Listeners** - Separates concerns
7. **Use Repository Pattern** - Abstract data access

### Phase 3: Nice-to-Have (Refactor for Polish)

8. **Extract Policy Base Class** - DRY up policies
9. **Create File Upload Configuration** - Centralize rules
10. **Improve Relationship Naming** - Consistency

---

## Testing Improvements Expected

Once violations are addressed:

- **Unit test coverage**: Can increase from ~40% to ~80%+
- **Integration tests**: Can focus on workflows vs. implementation details
- **Mock services easily**: Proper dependency injection enables this
- **Parallel test execution**: Less coupling = fewer conflicts

---

## Estimated Refactoring Effort

| Scope                | Effort    | Timeline |
| -------------------- | --------- | -------- |
| Critical Issues Only | 16 hours  | 2-3 days |
| Critical + Important | 32 hours  | 1 week   |
| Full Refactoring     | 48+ hours | 2 weeks+ |
