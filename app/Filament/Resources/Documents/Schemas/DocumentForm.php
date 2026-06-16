<?php

namespace App\Filament\Resources\Documents\Schemas;

use App\Models\Division;
use App\Models\User;
use Asmit\FilamentUpload\Forms\Components\AdvancedFileUpload;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class DocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Document Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        RichEditor::make('description')
                            ->required()
                            ->columnSpanFull(),
                        AdvancedFileUpload::make('file_path')
                            ->label('Document File')
                            ->required()
                            ->directory('documents')
                            ->storeFileNamesIn('file_name')
                            ->pdfPreviewHeight(420)
                            ->pdfToolbar(true)
                            ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/*'])
                            ->maxSize(10240)
                            ->columnSpanFull(),
                        Toggle::make('auto_approve')
                            ->label('Auto Approve')
                            ->helperText('Automatically approve the document when the limit date is reached.')
                            ->default(false)
                            ->live()
                            ->columnSpan(1),
                        DatePicker::make('limit_date')
                            ->label('Limit Date')
                            ->helperText('The date when the document will be auto-approved (if Auto Approve is enabled).')
                            ->native(false)
                            ->minDate(now()->startOfDay())
                            ->required(fn (Get $get): bool => (bool) $get('auto_approve'))
                            ->columnSpan(1),
                    ]),

                Section::make('Recipients')
                    ->schema([
                        Radio::make('recipient_selection_type')
                            ->label('Select Recipients By')
                            ->options([
                                'individual' => 'Individual Users',
                                'division' => 'Division',
                            ])
                            ->default('individual')
                            ->live()
                            ->required(),

                        Select::make('recipient_user_ids')
                            ->label('Select Recipients')
                            ->multiple()
                            ->options(
                                fn (): array => User::query()
                                    ->where('id', '!=', auth()->id())
                                    ->whereHas('roles', fn ($q) => $q->where('name', 'recipient'))
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray()
                            )
                            ->searchable()
                            ->visible(fn (Get $get): bool => $get('recipient_selection_type') === 'individual')
                            ->required(fn (Get $get): bool => $get('recipient_selection_type') === 'individual'),

                        Select::make('recipient_division_ids')
                            ->label('Select Divisions')
                            ->multiple()
                            ->options(
                                fn (): array => Division::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray()
                            )
                            ->searchable()
                            ->visible(fn (Get $get): bool => $get('recipient_selection_type') === 'division')
                            ->required(fn (Get $get): bool => $get('recipient_selection_type') === 'division'),
                    ]),
            ]);
    }
}
