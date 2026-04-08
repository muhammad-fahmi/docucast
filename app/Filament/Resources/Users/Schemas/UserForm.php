<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Personal Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('employee_no')
                            ->label('Employee No.')
                            ->maxLength(20)
                            ->unique(ignoreRecord: true),
                        TextInput::make('job_title')
                            ->maxLength(255),
                        Select::make('division_id')
                            ->label('Division')
                            ->relationship('division', 'name')
                            ->searchable()
                            ->preload(),
                        DateTimePicker::make('email_verified_at')
                            ->label('Email Verified At'),
                    ]),

                Section::make('Security')
                    ->schema([
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->required(fn(string $operation): bool => $operation === 'create')
                            ->dehydrateStateUsing(fn(?string $state): ?string => filled($state) ? bcrypt($state) : null)
                            ->dehydrated(fn(?string $state): bool => filled($state)),
                    ]),

                Section::make('Roles')
                    ->schema([
                        Select::make('roles')
                            ->label('Assigned Roles')
                            ->multiple()
                            ->live()
                            ->options(
                                fn(): array => Role::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'name')
                                    ->toArray()
                            )
                            ->disableOptionWhen(function (string $value, Get $get): bool {
                                $selectedRoles = $get('roles') ?? [];

                                if (!is_array($selectedRoles)) {
                                    return false;
                                }

                                $exclusiveRoles = ['super_admin', 'admin'];
                                $selectedExclusiveRoles = array_values(array_intersect($selectedRoles, $exclusiveRoles));
                                $selectedRegularRoles = array_values(array_diff($selectedRoles, $exclusiveRoles));

                                if (!empty($selectedExclusiveRoles)) {
                                    return $value !== $selectedExclusiveRoles[0];
                                }

                                return !empty($selectedRegularRoles) && in_array($value, $exclusiveRoles, true);
                            })
                            ->afterStateUpdated(function (?array $state, Set $set): void {
                                $roles = is_array($state) ? $state : [];
                                $exclusiveRoles = ['super_admin', 'admin'];
                                $selectedExclusiveRoles = array_values(array_intersect($roles, $exclusiveRoles));

                                if (!empty($selectedExclusiveRoles)) {
                                    $set('roles', [$selectedExclusiveRoles[0]]);
                                }
                            })
                            ->preload()
                            ->hint('Note: super_admin and admin cannot be combined with other roles.')
                            ->dehydrated(false)
                            ->afterStateHydrated(function (Select $component, $record): void {
                                if ($record) {
                                    $component->state($record->getRoleNames()->toArray());
                                }
                            }),
                    ]),
            ]);
    }
}
