@php
    $name = strtolower($document->file_name ?? $document->file_path ?? '');
    $isPdf = str_ends_with($name, '.pdf');
@endphp

<div class="space-y-4 rounded-xl border border-gray-200 bg-white p-4">

    @if ($isPdf)
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-3">
            @if (filled($document->description))
                <p class="mt-3 text-sm text-blue-900">
                    <span class="font-semibold">Uploader note:</span>
                </p>
                <quote class="mt-2 block rounded-lg border-l-4 border-blue-300 bg-blue-100 p-3 text-sm italic text-blue-900">
                    {{ $document->description }}
                </quote>
            @endif
        </div>
    @else
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
            This file type does not support embedded preview. Open it in a new tab first.
            <a href="{{ route('documents.preview', $document) }}" target="_blank" class="ml-1 font-semibold underline"
                rel="noopener noreferrer">
                Open document
            </a>
        </div>
    @endif
</div>