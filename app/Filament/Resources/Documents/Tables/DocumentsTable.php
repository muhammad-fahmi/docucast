<?php

namespace App\Filament\Resources\Documents\Tables;

use App\Models\Document;
use App\Models\DocumentReview;
use App\Notifications\RecipientSubmittedReviewNotification;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Joaopaulolndev\FilamentPdfViewer\Forms\Components\PdfViewerField;

class DocumentsTable
{
    public static function configure(Table $table): Table
    {
        $user = Auth::user();

        return $table
            ->poll('5s')
            ->modifyQueryUsing(function (Builder $query) use ($user): Builder {
                $query->with(['uploader:id,name']);
                $query->withExists([
                    'reviews as has_approved_reviews' => fn(Builder $reviewQuery): Builder => $reviewQuery->where('status', 'approved'),
                ]);

                if ($user?->hasRole('recipient')) {
                    $query->withExists([
                        'recipients as is_recipient' => fn(Builder $recipientQuery): Builder => $recipientQuery->where('users.id', $user->id),
                        'reviews as has_approved_review_by_user' => fn(Builder $reviewQuery): Builder => $reviewQuery
                            ->where('user_id', $user->id)
                            ->where('status', 'approved'),
                    ]);
                }

                return $query;
            })
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('unique_code')
                    ->label('Unique Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('uploader.name')
                    ->label('Uploaded By')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'warning',
                        'in_review' => 'info',
                        'approved' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('recipients_count')
                    ->label('Recipients')
                    ->counts('recipients')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'in_review' => 'In Review',
                        'approved' => 'Approved',
                    ]),
            ])
            ->recordActions([
                Action::make('review')
                    ->label('Review')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->modalHeading('Review Document')
                    ->modalWidth('7xl')
                    ->modalContent(fn($record) => view('filament.documents.review-modal-preview', [
                        'document' => $record,
                    ]))
                    ->schema([
                        PdfViewerField::make('document_preview')
                            ->label('Document Preview')
                            ->fileUrl(fn($record): ?string => $record ? route('documents.preview', $record) : null)
                            ->minHeight('58svh')
                            ->columnSpanFull()
                            ->dehydrated(false)
                            ->extraAttributes(['class' => 'hidden md:block'])
                            ->visible(fn($record): bool => str_ends_with(strtolower((string) ($record->file_name ?? $record->file_path)), '.pdf')),
                        Placeholder::make('open_pdf_mobile')
                            ->hiddenLabel()
                            ->dehydrated(false)
                            ->extraAttributes(['class' => 'md:hidden'])
                            ->visible(fn($record): bool => str_ends_with(strtolower((string) ($record->file_name ?? $record->file_path)), '.pdf'))
                            ->content(fn($record): HtmlString => new HtmlString(
                                '<a href="' . e(route('documents.preview', $record)) . '" target="_blank" rel="noopener noreferrer" class="inline-flex items-center rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-500">Open PDF in New Tab</a>'
                            )),
                        Radio::make('status')
                            ->label('Decision')
                            ->options([
                                'approved' => 'Approve',
                                'revision' => 'Request Revision',
                            ])
                            ->required(),
                        Textarea::make('message')
                            ->label('Message / Notes')
                            ->rows(3)
                            ->required(fn(\Filament\Schemas\Components\Utilities\Get $get): bool => $get('status') === 'revision'),
                    ])
                    ->action(function (array $data, $record) {
                        $user = Auth::user();

                        if (!$user) {
                            return;
                        }

                        $review = null;

                        DB::transaction(function () use ($data, $record, $user, &$review): void {
                            $document = Document::query()
                                ->whereKey($record->id)
                                ->lockForUpdate()
                                ->firstOrFail();

                            abort_unless($document->canRecipientSubmitReview($user), 403);

                            $now = now();

                            DocumentReview::query()->upsert(
                                [
                                    [
                                        'document_id' => $document->id,
                                        'user_id' => $user->id,
                                        'status' => $data['status'],
                                        'message' => $data['message'] ?? null,
                                        'created_at' => $now,
                                        'updated_at' => $now,
                                    ]
                                ],
                                ['document_id', 'user_id'],
                                ['status', 'message', 'updated_at'],
                            );

                            $document->updateStatusBasedOnReviews();

                            // Fetch the review for notification
                            $review = DocumentReview::where('document_id', $document->id)
                                ->where('user_id', $user->id)
                                ->with('reviewer')
                                ->first();
                        }, 3);

                        // Send notification to document uploader
                        if ($review) {
                            $uploader = $record->uploader;
                            if ($uploader) {
                                $uploader->notify(new RecipientSubmittedReviewNotification($record, $review));

                                $notificationBody = sprintf(
                                    '%s submitted %s for %s (%s).',
                                    $review->reviewer?->name ?? 'A recipient',
                                    strtoupper((string) $review->status),
                                    $record->title,
                                    $record->unique_code,
                                );

                                $dashboardNotification = FilamentNotification::make()
                                    ->title('New Document Review')
                                    ->body($notificationBody)
                                    ->viewData([
                                        'detail' => [
                                            'document_id' => $record->id,
                                            'document_title' => $record->title,
                                            'document_unique_code' => $record->unique_code,
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
                                $dashboardNotification->broadcast($uploader);
                            }
                        }
                    })
                    ->visible(function ($record): bool {
                        if (!Auth::check()) {
                            return false;
                        }

                        $user = Auth::user();

                        if (!$user->hasRole('recipient')) {
                            return false;
                        }

                        if (!(bool) ($record->is_recipient ?? false)) {
                            return false;
                        }

                        return !(bool) ($record->has_approved_review_by_user ?? false);
                    }),
                Action::make('allow_re_review')
                    ->label('Allow Re-Review')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->modalHeading('Allow Recipient to Review Again')
                    ->schema([
                        Select::make('recipient_user_id')
                            ->label('Recipient')
                            ->options(function ($record): array {
                                return $record->reviews()
                                    ->where('status', 'approved')
                                    ->with('reviewer:id,name')
                                    ->get()
                                    ->mapWithKeys(fn(DocumentReview $review): array => [$review->user_id => $review->reviewer?->name ?? (string) $review->user_id])
                                    ->toArray();
                            })
                            ->required()
                            ->searchable()
                            ->preload(),
                    ])
                    ->action(function (array $data, $record): void {
                        DB::transaction(function () use ($data, $record): void {
                            $document = Document::query()
                                ->whereKey($record->id)
                                ->lockForUpdate()
                                ->firstOrFail();

                            $document->allowRecipientToReviewAgain((int) $data['recipient_user_id']);
                        }, 3);
                    })
                    ->visible(function ($record): bool {
                        if (!Auth::check()) {
                            return false;
                        }

                        $user = Auth::user();
                        $canManageDocument = $user->hasAnyRole(['super_admin', 'admin']) || $record->uploader_id === $user->id;

                        if (!$canManageDocument) {
                            return false;
                        }

                        return (bool) ($record->has_approved_reviews ?? false);
                    }),
                Action::make('feedback')
                    ->label('Feedback')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('gray')
                    ->modalHeading('Recipient Feedback')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->infolist([
                        \Filament\Infolists\Components\RepeatableEntry::make('reviews')
                            ->label('Reviews')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('reviewer.name')
                                    ->label('Reviewer'),
                                \Filament\Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                        'approved' => 'success',
                                        'revision' => 'warning',
                                        default => 'gray',
                                    }),
                                \Filament\Infolists\Components\TextEntry::make('message')
                                    ->placeholder('No message provided')
                                    ->columnSpanFull(),
                                \Filament\Infolists\Components\TextEntry::make('updated_at')
                                    ->label('Updated')
                                    ->dateTime('d M Y H:i'),
                            ])
                            ->contained(false),
                    ])
                    ->visible(function ($record): bool {
                        if (!Auth::check()) {
                            return false;
                        }

                        $user = Auth::user();

                        return $user->hasAnyRole(['super_admin', 'admin']) || $record->uploader_id === $user->id;
                    }),
                EditAction::make()
                    ->visible(function ($record): bool {
                        if (!Auth::check()) {
                            return false;
                        }

                        $user = Auth::user();

                        return $user->hasAnyRole(['super_admin', 'admin']) || $record->uploader_id === $user->id;
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
