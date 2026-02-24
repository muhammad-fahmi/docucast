<?php

namespace App\Filament\Resources\Documents;

use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Models\Document;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Enums\Width;
use Filament\Notifications\Notification;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Select::make('reviewers') // Changed name to plural
                ->label('Assign Reviewers')
                ->multiple() // Allows selecting more than one person
                ->options(User::where('role', 'reviewer')->pluck('name', 'id')) // Strictly filters by role
                ->searchable()
                ->required(),

            FileUpload::make('document_file')
                ->label('PDF Document')
                ->acceptedFileTypes(['application/pdf'])
                ->directory('documents')
                ->disk('public') // <-- ADD THIS LINE
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('initiator.name')
                    ->label('Uploader')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('overall_status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'PENDING_REVIEW' => 'warning',
                        'NEEDS_REVISION' => 'danger',
                        'APPROVED' => 'success',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('reviewer_summary')
                    ->label('Review Status')
                    ->getStateUsing(function (Document $record) {
                        $user = Auth::user();

                        // If Uploader: show everyone
                        if ($record->initiator_id === $user->id || $user->role === 'admin') {
                            $approvals = $record->approvals()->with('reviewer')->get();
                        } else {
                            // If Reviewer: ONLY show their own status for privacy
                            $approvals = $record->approvals()
                                ->where('reviewer_id', $user->id)
                                ->with('reviewer')
                                ->get();
                        }

                        return $approvals->map(function ($app) {
                            $statusIcon = match ($app->status) {
                                'APPROVED' => '✅',
                                'REJECTED_FOR_REVISION' => '❌',
                                default => '⏳',
                            };
                            return "{$app->reviewer->name} {$statusIcon}";
                        })->implode('<br>');
                    })
                    ->html(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                // Add filters here later if needed
            ])
            ->actions([
                Action::make('preview')
                    ->label('Preview PDF')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn(Document $record) => $record->title)
                    // Using 'Full' width often helps the height scale better on wide monitors
                    ->modalWidth(Width::SevenExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    // This allows the modal to be taller by removing some internal margins
                    ->extraAttributes([
                        'class' => 'fi-modal-window-content-tall',
                        'style' => 'max-height: 95vh;'
                    ])
                    ->modalContent(function (Document $record) {
                        // This ensures that if Alice uploads V2, Bob sees V2, not the old V1
                        $latestVersion = $record->latestVersion;

                        if (!$latestVersion || !$latestVersion->file_storage_path) {
                            return 'No file found.';
                        }

                        return view('filament.components.pdf-preview', [
                            'path' => $latestVersion->file_storage_path,
                        ]);
                    }),
                // The custom Review Action
                Action::make('review')
                    ->label('Review')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    // Only show this button if it's pending review
                    ->visible(
                        fn(Document $record) =>
                        $record->approvals()
                            ->where('reviewer_id', Auth::id())
                            ->where('status', 'PENDING') // Button disappears once they click Approve/Revise
                            ->exists()
                    )
                    ->form([
                        ToggleButtons::make('decision')
                            ->label('Your Decision')
                            ->options([
                                'approve' => 'Approve',
                                'revise' => 'Request Revision',
                            ])
                            ->icons([
                                'approve' => 'heroicon-m-check-circle',
                                'revise' => 'heroicon-m-arrow-path',
                            ])
                            ->colors([
                                'approve' => 'success',
                                'revise' => 'danger',
                            ])
                            ->default('approve') // Optional: Default to approve to save time
                            ->required()
                            ->live() // Keep this for your "Revision Instructions" toggle
                            ->grouped(),

                        Textarea::make('comments')
                            ->label('Revision Instructions')
                            ->placeholder('Please describe what needs to be changed...')
                            // THIS IS THE TOGGLE LOGIC:
                            ->visible(fn(Get $get) => $get('decision') === 'revise')
                            // Ensure it's required only when visible
                            ->required(fn(Get $get) => $get('decision') === 'revise')
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data, Document $record): void {
                        $user = Auth::user();
                        $latestVersion = $record->latestVersion;
                        $approval = $record->approvals()->where('reviewer_id', $user->id)->first();

                        DB::transaction(function () use ($data, $record, $approval, $latestVersion, $user) {

                            // 1. Update only THIS specific reviewer's status
                            $status = ($data['decision'] === 'approve') ? 'APPROVED' : 'REJECTED_FOR_REVISION';

                            $approval->update([
                                'status' => $status,
                                'processed_at' => now()
                            ]);

                            // 2. Record this specific person's feedback in history
                            \App\Models\RevisionHistory::create([
                                'document_id' => $record->id,
                                'related_version_id' => $latestVersion->id,
                                'commenter_id' => $user->id,
                                'action_type' => $status,
                                'comments' => $data['comments'] ?? null,
                            ]);

                            // 3. RE-CALCULATE OVERALL STATUS
                            $allApprovals = $record->approvals()->get();

                            // Logic:
                            // - If anyone has REJECTED, the uploader needs to see "NEEDS_REVISION"
                            // - If everyone has APPROVED, status is "APPROVED"
                            // - Otherwise, it is still "PENDING_REVIEW"
                            if ($allApprovals->contains('status', 'REJECTED_FOR_REVISION')) {
                                $record->update(['overall_status' => 'NEEDS_REVISION']);
                            } elseif ($allApprovals->every(fn($app) => $app->status === 'APPROVED')) {
                                $record->update(['overall_status' => 'APPROVED']);
                            } else {
                                $record->update(['overall_status' => 'PENDING_REVIEW']);
                            }

                            // SEND THE NOTIFICATION
                            Notification::make()
                                ->title('Document Reviewed')
                                ->body("{$user->name} has marked '{$record->title}' as {$data['decision']}.")
                                ->icon(match ($data['decision']) {
                                    'approve' => 'heroicon-o-check-circle',
                                    'revise' => 'heroicon-o-exclamation-triangle',
                                })
                                ->color(match ($data['decision']) {
                                    'approve' => 'success',
                                    'revise' => 'danger',
                                })
                                // Add a button inside the notification to take them straight to the document
                                ->actions([
                                    Action::make('view')
                                        ->button()
                                        ->url(DocumentResource::getUrl('edit', ['record' => $record])),
                                ])
                                ->sendToDatabase($record->initiator)
                                ->broadcast($record->initiator);
                        });
                    }),
                Action::make('reupload')
                    ->label('Upload Revision')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    // ONLY show this to the original uploader, and ONLY if it needs revision
                    ->visible(fn(Document $record) => $record->overall_status === 'NEEDS_REVISION' && $record->initiator_id === Auth::id())
                    ->form([
                        FileUpload::make('new_document_file')
                            ->label('Upload Revised PDF')
                            ->acceptedFileTypes(['application/pdf'])
                            ->directory('documents')
                            ->disk('public') // <-- ADD THIS LINE
                            ->required(),
                    ])
                    ->action(function (array $data, Document $record): void {
                        $user = Auth::user();

                        DB::transaction(function () use ($data, $record, $user) {

                            // 1. Calculate the next version number
                            $currentVersionNumber = $record->versions()->max('version_number');
                            $newVersionNumber = $currentVersionNumber + 1;

                            // 2. Create the new Version entry linked to the same Master ID
                            $newVersion = \App\Models\DocumentVersion::create([
                                'document_id' => $record->id,
                                'version_number' => $newVersionNumber,
                                'file_storage_path' => $data['new_document_file'],
                                'uploaded_by' => $user->id,
                            ]);

                            // 3. Update the Master Document status back to pending
                            $record->update(['overall_status' => 'PENDING_REVIEW']);

                            // 4. Reset the Reviewer's Inbox so they see it again
                            $approval = \App\Models\DocumentApproval::where('document_id', $record->id)->first();
                            if ($approval) {
                                $approval->update([
                                    'status' => 'PENDING',
                                    'processed_at' => null, // Resets the "read" state
                                ]);
                            }

                            // 5. Log the resubmission in the audit trail
                            \App\Models\RevisionHistory::create([
                                'document_id' => $record->id,
                                'related_version_id' => $newVersion->id,
                                'commenter_id' => $user->id,
                                'action_type' => 'RESUBMITTED',
                                'comments' => "Uploaded revision v{$newVersionNumber}",
                            ]);
                        });
                    }),
                Action::make('history')
                    ->label('View History')
                    ->icon('heroicon-o-clock')
                    ->color('info')
                    ->modalHeading('Revision & Approval History')
                    ->modalSubmitAction(false) // Hides the "Submit" button since this is read-only
                    ->modalCancelActionLabel('Close')
                    ->infolist([
                        // RepeatableEntry loops through the 'revisionHistory' relationship we defined in the Model
                        RepeatableEntry::make('revisionHistory')
                            ->label('') // Hide the default label for a cleaner look
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Date')
                                    ->dateTime()
                                    ->icon('heroicon-m-calendar'),

                                TextEntry::make('commenter.name')
                                    ->label('User')
                                    ->icon('heroicon-m-user'),

                                TextEntry::make('action_type')
                                    ->label('Action Taken')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                        'SUBMITTED', 'RESUBMITTED' => 'info',
                                        'REQUESTED_REVISION' => 'danger',
                                        'APPROVED' => 'success',
                                        default => 'gray',
                                    }),

                                TextEntry::make('comments')
                                    ->label('Comments / Instructions')
                                    ->columnSpanFull()
                                    // Hide the comments label if there are no comments (like on initial upload)
                                    ->hidden(fn($state) => blank($state)),
                            ])
                            ->columns(3) // Arranges Date, User, and Action neatly in one row
                    ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        // Start with the default query
        $query = parent::getEloquentQuery();

        $user = Auth::user();

        // 1. If the user is an admin, return everything without filtering
        if ($user->role === 'admin') {
            return $query;
        }

        // 3. STRICT FILTER:
        return $query->where(function (Builder $subQuery) use ($user) {
            $subQuery->where('initiator_id', $user->id) // User is the uploader
                ->orWhereHas('approvals', function (Builder $approvalQuery) use ($user) {
                    $approvalQuery->where('reviewer_id', $user->id); // User is an assigned reviewer
                });
        });
    }



    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocuments::route('/'),
            'create' => CreateDocument::route('/create'),
            'edit' => EditDocument::route('/{record}/edit'),
        ];
    }
}
