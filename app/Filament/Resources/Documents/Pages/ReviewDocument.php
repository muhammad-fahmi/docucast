<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Models\Document;
use App\Models\DocumentReview;
use App\Notifications\RecipientSubmittedReviewNotification;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class ReviewDocument extends Page implements HasForms
{
    use InteractsWithForms;
    use InteractsWithRecord;

    protected static string $resource = DocumentResource::class;

    protected string $view = 'filament.resources.documents.pages.review-document';

    public ?array $data = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $user = Auth::user();
        abort_unless($user && $user->hasRole('recipient') && $this->record->canRecipientSubmitReview($user), 403);

        $this->form->fill();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Review Document: '.$this->record->title;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make([
                    'default' => 1,
                    'lg' => 12,
                ])
                    ->schema([
                        // Left Pane: Document preview (takes 7 cols on lg)
                        Group::make([
                            Placeholder::make('document_preview')
                                ->hiddenLabel()
                                ->dehydrated(false)
                                ->content(fn (): HtmlString => $this->record ? new HtmlString(
                                    str_ends_with(strtolower((string) ($this->record->file_name ?? $this->record->file_path)), '.pdf')
                                    ? '
                                    <!-- Desktop Layout: hidden on mobile, shown on md and up -->
                                    <div class="review-desktop-preview" style="height: 78vh;">
                                        <!-- Preview Header Bar -->
                                        <div class="review-preview-header">
                                            <div class="flex items-center space-x-3 truncate">
                                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-950/40 text-primary-600 dark:text-primary-400 shrink-0">
                                                    <svg class="h-4 w-4" width="16" height="16" style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                    </svg>
                                                </div>
                                                <div class="truncate">
                                                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-200 truncate">
                                                        '.e($this->record->file_name).'
                                                    </p>
                                                    <p class="text-[10px] text-gray-400 dark:text-gray-500">
                                                        PDF Document • '.e($this->record->unique_code).'
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="flex items-center space-x-2 shrink-0">
                                                <a href="'.e(route('documents.preview', ['document' => $this->record, 'v' => $this->record->updated_at?->timestamp])).'"
                                                   target="_blank"
                                                   rel="noopener noreferrer"
                                                   class="btn-primary"
                                                >
                                                    <svg class="h-3.5 w-3.5" width="14" height="14" style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                    </svg>
                                                    Open in New Tab
                                                </a>
                                            </div>
                                        </div>
                                        <!-- Preview Content -->
                                        <div class="bg-gray-50 dark:bg-gray-950" style="position: relative; flex: 1 1 0%;" x-data="{
                                            iframe: null,
                                            init() {
                                                this.iframe = this.$el.querySelector(\'iframe\');
                                            },
                                            destroy() {
                                                if (this.iframe) {
                                                    this.iframe.src = \'about:blank\';
                                                }
                                            }
                                        }">
                                            <iframe src="'.e(route('documents.preview', ['document' => $this->record, 'v' => $this->record->updated_at?->timestamp])).'" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;"></iframe>
                                        </div>
                                    </div>

                                    <!-- Mobile Layout: hidden on desktop, shown below md -->
                                    <div class="review-mobile-preview">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-50 dark:bg-primary-950/30 text-primary-500 mx-auto">
                                            <svg class="w-6 h-6" width="24" height="24" style="width: 24px; height: 24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Mobile PDF Preview</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-medium">PDF preview cannot be embedded directly on mobile devices.</p>
                                        </div>
                                        <a href="'.e(route('documents.preview', ['document' => $this->record, 'v' => $this->record->updated_at?->timestamp])).'"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           class="btn-mobile-primary"
                                        >
                                            Open PDF in New Tab
                                        </a>
                                    </div>
                                    '
                                    : '
                                    <!-- Non-PDF Layout -->
                                    <div class="review-non-pdf-preview" style="height: 78vh;">
                                        <!-- Preview Header Bar -->
                                        <div class="review-preview-header">
                                            <div class="flex items-center space-x-3 truncate">
                                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-950/40 text-primary-600 dark:text-primary-400 shrink-0">
                                                    <svg class="h-4 w-4" width="16" height="16" style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                    </svg>
                                                </div>
                                                <div class="truncate">
                                                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-200 truncate">
                                                        '.e($this->record->file_name).'
                                                    </p>
                                                    <p class="text-[10px] text-gray-400 dark:text-gray-500">
                                                        File • '.e($this->record->unique_code).'
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Preview Content -->
                                        <div class="review-non-pdf-content">
                                            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-50 dark:bg-primary-950/30 text-primary-500" style="display: flex; align-items: center; justify-content: center;">
                                                <svg class="w-8 h-8" width="32" height="32" style="width: 32px; height: 32px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-base text-gray-800 dark:text-gray-200">No Embedded Preview Available</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-xs mx-auto">This file type cannot be previewed directly inside the page. You can open it in a new window or download it to view.</p>
                                            </div>
                                            <a href="'.e(route('documents.preview', ['document' => $this->record, 'v' => $this->record->updated_at?->timestamp])).'"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="btn-primary"
                                            >
                                                <svg class="h-4 w-4" width="16" height="16" style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                </svg>
                                                Download / Open Document
                                            </a>
                                        </div>
                                    </div>
                                    '
                                ) : new HtmlString('')),
                        ])
                            ->columnSpan(['lg' => 7]),

                        // Right Pane: Tabbed review details, history, recipients and form (takes 5 cols on lg)
                        Group::make([
                            Tabs::make('Review Workspace')
                                ->tabs([
                                    Tab::make('Review Action')
                                        ->icon('heroicon-m-clipboard-document-check')
                                        ->schema([
                                            Radio::make('status')
                                                ->label('Decision')
                                                ->options([
                                                    'approved' => 'Approve',
                                                    'revision' => 'Request Revision',
                                                ])
                                                ->required()
                                                ->live(),

                                            Textarea::make('message')
                                                ->label('Message / Notes')
                                                ->rows(4)
                                                ->placeholder('Write your review feedback, comments, or revision requests here...')
                                                ->required(fn (Get $get): bool => $get('status') === 'revision'),

                                            FileUpload::make('attachment_path')
                                                ->label('Attachment (Optional)')
                                                ->helperText('Upload a marked-up document, screenshot, or signature if needed.')
                                                ->directory('review-attachments')
                                                ->storeFileNamesIn('attachment_name')
                                                ->maxSize(5120) // 5MB
                                                ->acceptedFileTypes(['application/pdf', 'image/*', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']),
                                        ]),

                                    Tab::make('Document Info')
                                        ->icon('heroicon-m-information-circle')
                                        ->schema([
                                            Placeholder::make('document_info_details')
                                                ->hiddenLabel()
                                                ->content(fn () => $this->record ? view('filament.documents.review-document-details', [
                                                    'document' => $this->record,
                                                ]) : ''),
                                        ]),

                                    Tab::make('History')
                                        ->icon('heroicon-m-clock')
                                        ->schema([
                                            Placeholder::make('document_history')
                                                ->hiddenLabel()
                                                ->content(fn () => $this->record ? view('filament.documents.review-document-history', [
                                                    'document' => $this->record,
                                                ]) : ''),
                                        ]),

                                    Tab::make('Recipients')
                                        ->icon('heroicon-m-users')
                                        ->schema([
                                            Placeholder::make('document_recipients')
                                                ->hiddenLabel()
                                                ->content(fn () => $this->record ? view('filament.documents.review-document-recipients', [
                                                    'document' => $this->record,
                                                ]) : ''),
                                        ]),
                                ]),
                        ])
                            ->columnSpan(['lg' => 5]),
                    ]),
            ])
            ->statePath('data');
    }

    public function submitReview(): void
    {
        $user = Auth::user();
        abort_unless($user && $this->record->canRecipientSubmitReview($user), 403);

        $formData = $this->form->getState();

        $review = null;

        DB::transaction(function () use ($formData, $user, &$review): void {
            $document = Document::query()
                ->whereKey($this->record->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Resolve the current (latest) version — this is what the user is reviewing
            $currentVersionId = $document->versions()
                ->max('id');

            abort_unless($currentVersionId, 500, 'Document has no version.');

            $now = now();

            DocumentReview::query()->upsert(
                [
                    [
                        'document_id'         => $document->id,
                        'document_version_id' => $currentVersionId,
                        'user_id'             => $user->id,
                        'status'              => $formData['status'],
                        'message'             => $formData['message'] ?? null,
                        'attachment_path'     => $formData['attachment_path'] ?? null,
                        'attachment_name'     => $formData['attachment_name'] ?? null,
                        'created_at'          => $now,
                        'updated_at'          => $now,
                    ],
                ],
                ['document_version_id', 'user_id'],
                ['status', 'message', 'attachment_path', 'attachment_name', 'updated_at'],
            );

            $document->updateStatusBasedOnReviews();

            // Fetch the review for notification
            $review = DocumentReview::where('document_id', $document->id)
                ->where('document_version_id', $currentVersionId)
                ->where('user_id', $user->id)
                ->with('reviewer')
                ->first();
        }, 3);

        if ($review) {
            $uploader = $this->record->uploader;
            if ($uploader) {
                $uploader->notify(new RecipientSubmittedReviewNotification($this->record, $review));

                $notificationBody = sprintf(
                    '%s submitted %s for %s (%s).',
                    $review->reviewer?->name ?? 'A recipient',
                    strtoupper((string) $review->status),
                    $this->record->title,
                    $this->record->unique_code,
                );

                $dashboardNotification = FilamentNotification::make()
                    ->title('New Document Review')
                    ->body($notificationBody)
                    ->viewData([
                        'detail' => [
                            'document_id' => $this->record->id,
                            'document_title' => $this->record->title,
                            'document_unique_code' => $this->record->unique_code,
                            'review_id' => $review->id,
                            'review_status' => $review->status,
                            'review_message' => $review->message,
                            'reviewer_name' => $review->reviewer?->name,
                        ],
                    ]);

                if ($review->status === 'revision') {
                    $dashboardNotification->warning();
                } else {
                    $dashboardNotification->success();
                }

                $dashboardNotification->sendToDatabase($uploader);
                try {
                    $dashboardNotification->broadcast($uploader);
                } catch (\Exception $e) {
                    // Ignore broadcast exceptions
                }
            }
        }

        FilamentNotification::make()
            ->title('Review Submitted Successfully')
            ->success()
            ->send();

        $this->redirect(DocumentResource::getUrl('index'));
    }
}
