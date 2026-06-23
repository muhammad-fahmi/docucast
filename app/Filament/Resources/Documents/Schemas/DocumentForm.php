<?php

namespace App\Filament\Resources\Documents\Schemas;

use App\Filament\Resources\Documents\Widgets\DocumentTimelineWidget;
use App\Models\Division;
use App\Models\Document;
use App\Models\User;
use Asmit\FilamentUpload\Forms\Components\AdvancedFileUpload;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class DocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Preview Dokumen')
                    ->description(new HtmlString('
                        <style>
                            @media (min-width: 1024px) {
                                .preview-sync-section {
                                    position: sticky !important;
                                    top: 2rem !important;
                                    height: calc(100vh - 10rem) !important;
                                    align-self: flex-start !important;
                                    display: flex;
                                    flex-direction: column;
                                    z-index: 10;
                                }
                                .preview-sync-section .fi-section-content-ctn,
                                .preview-sync-section .fi-section-content,
                                .preview-sync-section .fi-section-content > div,
                                .preview-sync-section .fi-fo-field-wrp,
                                .preview-sync-section .fi-fo-field-wrp > div,
                                .preview-sync-section .filepond--root {
                                    display: flex;
                                    flex-direction: column;
                                    flex-grow: 1;
                                }
                                .preview-sync-section .filepond--root {
                                    margin-bottom: 0 !important;
                                }
                                .preview-sync-section .filepond--pdf-preview-iframe {
                                    height: 100% !important;
                                    min-height: 500px;
                                }
                            }
                        </style>
                    '))
                    ->extraAttributes(['class' => 'preview-sync-section'])
                    ->schema([
                        AdvancedFileUpload::make('file_path')
                            ->label('Document File')
                            ->required()
                            ->markAsRequired(false)
                            ->hint('(*Wajib Diisi)')
                            ->hintColor('danger')
                            ->directory('documents')
                            ->storeFileNamesIn('file_name')
                            ->pdfToolbar(true)
                            ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/*'])
                            ->maxSize(10240)
                            ->pdfPreviewHeight(700)
                            ->columnSpanFull(),
                    ]),

                Section::make('Properti Dokumen')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->markAsRequired(false)
                            ->hint('(*Wajib Diisi)')
                            ->hintColor('danger')
                            ->label('Nama Dokumen')
                            ->maxLength(255)
                            ->placeholder('Masukkan nama dokumen disini')
                            ->columnSpanFull(),
                        RichEditor::make('description')
                            ->required()
                            ->markAsRequired(false)
                            ->hint('(*Wajib Diisi)')
                            ->hintColor('danger')
                            ->label('Deskripsi Dokumen')
                            ->placeholder('Masukkan deskripsi disini')
                            ->columnSpanFull(),
                        Radio::make('recipient_selection_type')
                            ->label('Pilih Penerima Berdasarkan:')
                            ->options([
                                'individual' => 'Individu/Perorangan',
                                'division' => 'Divisi',
                            ])
                            ->default('individual')
                            ->live()
                            ->required()
                            ->markAsRequired(false)
                            ->hint('(*Wajib Diisi)')
                            ->hintColor('danger'),

                        Select::make('recipient_user_ids')
                            ->label('Pilih Penerima')
                            ->placeholder('Pilih Opsi')
                            ->multiple()
                            ->options(
                                fn(): array => User::query()
                                    ->where('id', '!=', Auth::id())
                                    ->whereHas('roles', fn($q) => $q->where('name', 'recipient'))
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray()
                            )
                            ->searchable()
                            ->loadingMessage('Memuat Data Karyawan...')
                            ->searchPrompt('Masukkan Nama Karyawan')
                            ->visible(fn(Get $get): bool => $get('recipient_selection_type') === 'individual')
                            ->required(fn(Get $get): bool => $get('recipient_selection_type') === 'individual')
                            ->markAsRequired(false)
                            ->hint('(*Wajib Diisi)')
                            ->hintColor('danger'),

                        Select::make('recipient_division_ids')
                            ->label('Pilih Divisi')
                            ->multiple()
                            ->options(
                                fn(): array => Division::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray()
                            )
                            ->searchable()
                            ->visible(fn(Get $get): bool => $get('recipient_selection_type') === 'division')
                            ->required(fn(Get $get): bool => $get('recipient_selection_type') === 'division')
                            ->markAsRequired(false)
                            ->hint('(*Wajib Diisi)')
                            ->hintColor('danger'),
                        Section::make('Approval Settings')
                            ->columns(2)
                            ->schema([
                                Toggle::make('auto_approve')
                                    ->label('Auto Approve')
                                    ->helperText('Automatically approve the document when the limit date is reached.')
                                    ->default(false)
                                    ->live()
                                    ->columnSpan(1),
                                DatePicker::make('limit_date')
                                    ->hidden(fn(Get $get): bool => ! (bool) $get('auto_approve'))
                                    ->label('Limit Date')
                                    ->helperText('The date when the document will be auto-approved (if Auto Approve is enabled).')
                                    ->native(false)
                                    ->minDate(fn(?Document $record) => $record?->created_at ? $record->created_at->clone()->addDay() : now()->addDay())
                                    ->required(fn(Get $get): bool => (bool) $get('auto_approve'))
                                    ->columnSpan(1),
                            ]),
                        Livewire::make(DocumentTimelineWidget::class)
                            ->columnSpanFull()
                            ->visible(fn(?Document $record) => $record !== null),
                    ]),

            ]);
    }
}
