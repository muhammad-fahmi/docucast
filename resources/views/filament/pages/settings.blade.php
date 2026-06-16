<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::actions :actions="$this->getFormActions()"></x-filament::actions>
        </div>
    </form>
</x-filament-panels::page>