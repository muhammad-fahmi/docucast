@php
    $versions = $document->versions()->with('uploader')->orderByDesc('version_number')->get();
@endphp

<div style="display: flex; flex-direction: column; gap: var(--spacing-gr-lg);">
    <!-- Document Metadata Card -->
    <div class="review-info-card">
        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Document Info</h4>
        <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--spacing-gr-md); margin-top: var(--spacing-gr-sm);" class="text-sm">
            <div>
                <span class="text-xs text-gray-500 dark:text-gray-400">Title:</span>
                <p class="font-semibold text-gray-800 dark:text-gray-200 truncate" title="{{ $document->title }}">{{ $document->title }}</p>
            </div>
            <div>
                <span class="text-xs text-gray-500 dark:text-gray-400">Code:</span>
                <p class="font-semibold text-gray-800 dark:text-gray-200">{{ $document->unique_code }}</p>
            </div>
            <div>
                <span class="text-xs text-gray-500 dark:text-gray-400">Uploader:</span>
                <p class="font-medium text-gray-700 dark:text-gray-300">{{ $document->uploader?->name ?? 'System' }}</p>
            </div>
            @if ($document->limit_date)
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Limit Date:</span>
                    <p class="font-medium text-gray-700 dark:text-gray-300">
                        {{ $document->limit_date->format('d M Y') }}
                        @if ($document->auto_approve)
                            <span class="text-[10px] bg-primary-50 text-primary-700 dark:bg-primary-950/30 dark:text-primary-400 px-1.5 py-0.5 rounded font-semibold ml-1">Auto-Approve</span>
                        @endif
                    </p>
                </div>
            @endif
        </div>

        @if (filled($document->description))
            <div class="border-t border-gray-200 dark:border-gray-800" style="margin-top: var(--spacing-gr-md); padding-top: var(--spacing-gr-sm);">
                <span class="text-xs text-gray-500 dark:text-gray-400 font-semibold block mb-2">Description:</span>
                <div class="rich-text-content text-sm p-3.5 rounded-lg border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 text-gray-700 dark:text-gray-300">
                    {!! $document->description !!}
                </div>
            </div>
        @endif
    </div>

    <!-- Version History -->
    <div style="display: flex; flex-direction: column; gap: var(--spacing-gr-sm);">
        <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center">
            <svg class="w-4 h-4 mr-1.5 text-gray-400 -mt-px" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path>
            </svg>
            Version History ({{ $versions->count() }})
        </h3>

        <div class="review-version-list">
            @foreach ($versions as $version)
                <div class="review-version-item">
                    <!-- Column 1: Version label -->
                    <div class="review-version-label-container">
                        <span class="review-version-label-text">
                            v{{ $version->version_number }}
                        </span>
                    </div>
                    <!-- Column 2: Title with uploader info -->
                    <div style="flex: 1 1 0%; min-width: 0; padding-right: var(--spacing-gr-sm);">
                        <p class="font-medium text-gray-900 dark:text-white truncate" style="margin: 0;" title="{{ $version->original_filename }}">
                            {{ $version->original_filename ?: 'Document' }}
                        </p>
                        <p class="text-gray-500 dark:text-gray-400 truncate" style="margin-top: var(--spacing-gr-xs); font-size: 0.75rem;">
                            Uploaded by {{ $version->uploader?->name ?? 'System' }} • {{ $version->created_at->diffForHumans() }}
                        </p>
                    </div>
                    <!-- Column 3: Download button -->
                    <div style="flex-shrink: 0; display: flex; align-items: center;">
                        <a 
                            href="{{ route('documents.preview', ['document' => $document, 'version' => $version->version_number]) }}" 
                            target="_blank" 
                            rel="noopener noreferrer"
                            class="review-download-btn"
                        >
                            <svg class="h-3.5 w-3.5 text-gray-400" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Download
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
