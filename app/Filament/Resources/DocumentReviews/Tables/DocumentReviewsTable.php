<?php

namespace App\Filament\Resources\DocumentReviews\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DocumentReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query): Builder => $query->with(['document:id,title', 'reviewer:id,name']))
            ->columns([
                TextColumn::make('document.title')
                    ->label('Document')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('reviewer.name')
                    ->label('Reviewer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'approved' => 'success',
                        'revision' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('message')
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Reviewed At')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'approved' => 'Approved',
                        'revision' => 'Revision Requested',
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
