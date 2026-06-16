@php
    $name = strtolower($document->file_name ?? $document->file_path ?? '');
    $isPdf = str_ends_with($name, '.pdf');
@endphp

<div class="space-y-4 rounded-xl border border-gray-200 bg-white p-4">

    @if ($isPdf)
        @if (filled($document->description))
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-3">
                <p class="mt-3 text-sm text-blue-900">
                    <span class="font-semibold block mb-2">Description:</span>
                </p>
                <div class="rich-text-content text-sm block rounded-lg border-l-4 border-blue-300 bg-blue-100 p-3 text-blue-900">
                    {!! $document->description !!}
                </div>
            </div>
        @endif
    @else
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
            This file type does not support embedded preview. Open it in a new tab first.
            <a href="{{ route('documents.preview', ['document' => $document, 'v' => $document->updated_at?->timestamp]) }}" target="_blank" class="ml-1 font-semibold underline"
                rel="noopener noreferrer">
                Open document
            </a>
        </div>
    @endif
</div>