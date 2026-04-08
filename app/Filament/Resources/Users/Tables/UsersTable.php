<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query): Builder => $query->with(['division:id,name', 'roles:id,name']))
            ->columns([
                TextColumn::make('employee_no')
                    ->label('Employee No.')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email Address')
                    ->searchable(),
                TextColumn::make('job_title')
                    ->label('Job Title')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('division.name')
                    ->label('Division')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->color('warning'),
                TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('division')
                    ->relationship('division', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('roles')
                    ->label('Role')
                    ->relationship('roles', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
