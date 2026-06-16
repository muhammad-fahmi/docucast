<?php

use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Schemas\DocumentForm;
use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;

uses(RefreshDatabase::class);

it('disables create another option', function () {
    $reflection = new ReflectionClass(CreateDocument::class);
    $property = $reflection->getProperty('canCreateAnother');
    $property->setAccessible(true);

    expect($property->getValue(new CreateDocument))->toBeFalse();
});

it('redirects to the index page after document creation', function () {
    $page = new CreateDocument;

    $reflection = new ReflectionClass(CreateDocument::class);
    $method = $reflection->getMethod('getRedirectUrl');
    $method->setAccessible(true);

    $redirectUrl = $method->invoke($page);

    expect($redirectUrl)->toBe(DocumentResource::getUrl('index'));
});

it('requires a description using RichEditor component', function () {
    $livewire = Mockery::mock(Component::class, HasSchemas::class);
    $schema = Schema::make($livewire);
    DocumentForm::configure($schema);

    $components = $schema->getComponents();
    $section = $components[0];

    $descriptionField = collect($section->getChildComponents())
        ->firstWhere(fn ($component) => method_exists($component, 'getName') && $component->getName() === 'description');

    expect($descriptionField)->not->toBeNull()
        ->and($descriptionField)->toBeInstanceOf(RichEditor::class)
        ->and($descriptionField->isRequired())->toBeTrue();
});
