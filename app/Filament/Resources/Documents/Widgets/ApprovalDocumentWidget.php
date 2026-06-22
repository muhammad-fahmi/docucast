<?php

namespace App\Filament\Resources\Documents\Widgets;

use App\Filament\Resources\Documents\DocumentResource;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use App\Models\Document;
use App\Models\DocumentReview;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Widgets\TableWidget as BaseWidget;

class ApprovalDocumentWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Documents Pending Your Review';

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $user = Auth::user();

                $query = Document::query()
                    ->with(['uploader:id,name']);

                if ($user->hasRole('recipient')) {
                    $query
                        ->where('status', 'in_review')
                        ->whereHas('recipients', fn($q) => $q->where('users.id', $user->id))
                        ->whereDoesntHave(
                            'reviews',
                            fn($q) => $q
                                ->where('user_id', $user->id)
                                ->where('status', 'approved')
                        );
                } elseif ($user->hasAnyRole(['super_admin', 'admin'])) {
                    $query->where('status', 'in_review');
                } else {
                    $query
                        ->where('uploader_id', $user->id)
                        ->where('status', 'in_review');
                }

                return $query;
            })
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M Y, g:i A')
                    ->sortable(),
                TextColumn::make('title')
                    ->label('File Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('uploader.name')
                    ->label('Uploaded By')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending'   => 'warning',
                        'in_review' => 'info',
                        'approved'  => 'success',
                        default     => 'gray',
                    })
                    ->sortable(),
            ])
            ->recordAction(null)
            ->recordUrl(null)
            ->recordActions([
                Action::make('review')
                    ->label('Review')
                    ->icon('heroicon-o-check-circle')
                    ->color('info')
                    ->url(fn($record): string => DocumentResource::getUrl('review', ['record' => $record])),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('No documents to review')
            ->emptyStateDescription('You have no pending documents awaiting your review.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
