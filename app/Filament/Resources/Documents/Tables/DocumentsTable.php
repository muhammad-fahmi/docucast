<?php

namespace App\Filament\Resources\Documents\Tables;

use App\Filament\Resources\Documents\DocumentResource;
use App\Models\Document;
use App\Models\DocumentReview;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;

class DocumentsTable
{
    public static function configure(Table $table): Table
    {
        $user = Auth::user();

        return $table
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
                    ->sortable()
                    ->url(fn($record): string => DocumentResource::getUrl('history', ['record' => $record]))
                    ->color('primary'),
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
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->url(fn($record): string => DocumentResource::getUrl('review', ['record' => $record]))
                    ->visible(function ($record): bool {
                        if (! Auth::check()) {
                            return false;
                        }

                        $user = Auth::user();

                        if (! $user->hasRole('recipient')) {
                            return false;
                        }

                        if (! (bool) ($record->is_recipient ?? false)) {
                            return false;
                        }

                        return ! (bool) ($record->has_approved_review_by_user ?? false);
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
                        if (! Auth::check()) {
                            return false;
                        }

                        $user = Auth::user();
                        $canManageDocument = $user->hasAnyRole(['super_admin', 'admin']) || $record->uploader_id === $user->id;

                        if (! $canManageDocument) {
                            return false;
                        }

                        return (bool) ($record->has_approved_reviews ?? false);
                    }),
                Action::make('feedback')
                    // ->label('Feedback')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('gray')
                    ->modalHeading('Recipient Feedback')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->infolist([
                        RepeatableEntry::make('reviews')
                            ->hiddenLabel()
                            ->placeholder('No feedback has been submitted yet.')
                            ->schema([
                                TextEntry::make('reviewer.name')
                                    ->weight(\Filament\Support\Enums\FontWeight::SemiBold)
                                    ->icon('heroicon-o-user-circle'),
                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                        'approved' => 'success',
                                        'revision' => 'warning',
                                        default => 'gray',
                                    }),
                                TextEntry::make('updated_at')
                                    ->dateTime('d M Y · H:i')
                                    ->icon('heroicon-o-clock')
                                    ->color('gray'),
                                TextEntry::make('message')
                                    ->placeholder('No message provided')
                                    ->columnSpanFull()
                                    ->extraAttributes(['class' => 'rounded-lg bg-gray-50 dark:bg-white/5 p-3 text-sm']),
                                TextEntry::make('attachment_name')
                                    ->label('Attachment')
                                    ->placeholder('No attachment')
                                    ->columnSpanFull()
                                    ->formatStateUsing(function ($state, $record): string {
                                        if (! filled($record->attachment_path)) {
                                            return e($state ?? '');
                                        }

                                        $downloadUrl = route('filament.documents.attachment.download', ['review' => $record->id]);
                                        $previewUrl  = route('filament.documents.attachment.preview', ['review' => $record->id]);

                                        return Blade::render(
                                            '<div>
                                                <span class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 px-3 py-1.5 text-sm shadow-sm">
                                                    <a href="{{ $download }}" class="text-primary-600 dark:text-primary-400 underline hover:text-primary-500">{{ $name }}</a>
                                                     <a href="{{ $preview }}" target="_blank" title="Preview in new tab" class="text-gray-400 hover:text-primary-500 transition-colors shrink-0">
                                                        [Preview]                                              
                                                     </a>
                                                </span>
                                            </div>
                                            <hr class="border-t border-gray-200 dark:border-white/10 mt-6" />',
                                            ['download' => $downloadUrl, 'preview' => $previewUrl, 'name' => $state]
                                        );
                                    })

                                    ->html()
                                    ->visible(fn($record): bool => filled($record->attachment_path)),
                            ])
                            ->columns(3)

                            ->contained(false),
                    ])
                    ->visible(function ($record): bool {
                        if (! Auth::check()) {
                            return false;
                        }

                        $user = Auth::user();

                        return $user->hasAnyRole(['super_admin', 'admin']) || $record->uploader_id === $user->id;
                    }),
                EditAction::make()
                    ->visible(function ($record): bool {
                        if (! Auth::check()) {
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
