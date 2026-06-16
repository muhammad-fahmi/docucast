@php
    $reviews = $document->reviews()->with('reviewer')->latest('updated_at')->get();
@endphp

<div style="display: flex; flex-direction: column; gap: var(--spacing-gr-md);">
    <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center">
        <svg class="w-4 h-4 mr-1.5 text-gray-400 -mt-px" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
        </svg>
        Review History ({{ $reviews->count() }})
    </h3>

    @if ($reviews->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-200 dark:border-gray-800 p-6 text-center">
            <p class="text-sm text-gray-500 dark:text-gray-400">No reviews submitted yet.</p>
        </div>
    @else
        <div class="flow-root">
            <ul role="list" class="-mb-8">
                @foreach ($reviews as $index => $review)
                    <li>
                        <div class="relative" style="padding-bottom: var(--spacing-gr-lg);">
                            @if ($index !== $reviews->count() - 1)
                                <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-800" aria-hidden="true"></span>
                            @endif
                            <div class="relative flex space-x-3">
                                <div>
                                    @if ($review->status === 'approved')
                                        <span class="h-8 w-8 rounded-full bg-emerald-50 dark:bg-emerald-950/30 flex items-center justify-center ring-8 ring-white dark:ring-gray-900">
                                            <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400" width="20" height="20" style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </span>
                                    @else
                                        <span class="h-8 w-8 rounded-full bg-amber-50 dark:bg-amber-950/30 flex items-center justify-center ring-8 ring-white dark:ring-gray-900">
                                            <svg class="h-5 w-5 text-amber-600 dark:text-amber-400" width="20" height="20" style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                        </span>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0 pt-1.5">
                                    <div class="flex items-center justify-between space-x-4">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                                {{ $review->reviewer?->name ?? 'Unknown User' }}
                                            </p>
                                        </div>
                                        <div class="text-right text-xs whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            <time datetime="{{ $review->updated_at->toIso8601String() }}">
                                                {{ $review->updated_at->diffForHumans() }}
                                            </time>
                                        </div>
                                    </div>
                                    <div class="mt-1 flex items-center gap-2">
                                        @if ($review->status === 'approved')
                                            <span class="inline-flex items-center rounded-md bg-emerald-50 dark:bg-emerald-950/30 px-2 py-1 text-xs font-medium text-emerald-700 dark:text-emerald-400 ring-1 ring-inset ring-emerald-600/20 dark:ring-emerald-400/20">
                                                Approved
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-md bg-amber-50 dark:bg-amber-950/30 px-2 py-1 text-xs font-medium text-amber-700 dark:text-amber-400 ring-1 ring-inset ring-amber-600/20 dark:ring-amber-400/20">
                                                Revision Requested
                                            </span>
                                        @endif
                                    </div>
                                    @if (filled($review->message))
                                        <div class="text-sm text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-900/50 rounded-lg border border-gray-200 dark:border-gray-800/80" style="margin-top: var(--spacing-gr-xs); padding: var(--spacing-gr-sm);">
                                            {{ $review->message }}
                                        </div>
                                    @endif
                                    @if ($review->attachment_path)
                                        <div style="margin-top: var(--spacing-gr-xs);">
                                            <a 
                                                href="{{ Storage::url($review->attachment_path) }}" 
                                                target="_blank" 
                                                rel="noopener noreferrer"
                                                class="inline-flex items-center gap-1 text-xs text-primary-600 hover:text-primary-500 font-semibold underline dark:text-primary-400"
                                            >
                                                <svg class="w-3.5 h-3.5 -mt-px" width="14" height="14" style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                                </svg>
                                                {{ $review->attachment_name ?: 'Attachment' }}
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
