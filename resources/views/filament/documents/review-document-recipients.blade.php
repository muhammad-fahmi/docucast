@php
    $reviews = $document->reviews()->with('reviewer')->latest('updated_at')->get();
    $reviewedUserIds = $reviews->pluck('user_id')->toArray();
    $pendingRecipients = $document->recipients()
        ->whereNotIn('users.id', $reviewedUserIds)
        ->orderBy('name')
        ->get();
    
    $completedRecipients = $document->recipients()
        ->whereIn('users.id', $reviewedUserIds)
        ->orderBy('name')
        ->get();
@endphp

<div style="display: flex; flex-direction: column; gap: var(--spacing-gr-lg);">
    <!-- Pending Reviewers -->
    <div style="display: flex; flex-direction: column; gap: var(--spacing-gr-sm);">
        <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center">
            <svg class="w-4 h-4 mr-1.5 text-gray-400 -mt-px" width="16" height="16" style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Pending Reviewers ({{ $pendingRecipients->count() }})
        </h3>

        @if ($pendingRecipients->isEmpty())
            <div class="rounded-xl bg-emerald-50/40 dark:bg-emerald-950/10 border border-emerald-100/50 dark:border-emerald-900/20 p-4 flex items-center text-xs text-emerald-800 dark:text-emerald-400 gap-2 shadow-sm">
                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" width="16" height="16" style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-medium">All recipients have responded to this document.</span>
            </div>
        @else
            <div style="display: flex; flex-wrap: wrap; gap: var(--spacing-gr-xs);">
                @foreach ($pendingRecipients as $pending)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300 border border-gray-200/50 dark:border-gray-700/50 shadow-sm transition hover:bg-gray-200 dark:hover:bg-gray-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                        {{ $pending->name }}
                    </span>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Completed Reviewers -->
    <div class="border-t border-gray-200 dark:border-gray-800" style="display: flex; flex-direction: column; gap: var(--spacing-gr-sm); padding-top: var(--spacing-gr-md);">
        <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center">
            <svg class="w-4 h-4 mr-1.5 text-gray-400 -mt-px" width="16" height="16" style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Responded Reviewers ({{ $completedRecipients->count() }})
        </h3>

        @if ($completedRecipients->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-200 dark:border-gray-800 p-4 text-center">
                <p class="text-xs text-gray-500 dark:text-gray-400">No responses yet.</p>
            </div>
        @else
            <div style="display: flex; flex-wrap: wrap; gap: var(--spacing-gr-xs);">
                @foreach ($completedRecipients as $completed)
                    @php
                        $review = $reviews->firstWhere('user_id', $completed->id);
                        $statusClass = $review && $review->status === 'approved' ? 'bg-emerald-500' : 'bg-amber-500';
                    @endphp
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300 border border-gray-200/50 dark:border-gray-700/50 shadow-sm">
                        <span class="h-1.5 w-1.5 rounded-full {{ $statusClass }}"></span>
                        {{ $completed->name }}
                    </span>
                @endforeach
            </div>
        @endif
    </div>
</div>
