<div 
    x-show="showConfirm" 
    x-cloak
    class="fixed inset-0 z-[100] overflow-y-auto"
    aria-labelledby="modal-title" 
    role="dialog" 
    aria-modal="true"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
>
    <!-- Backdrop blur background -->
    <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div 
            class="fixed inset-0 bg-gray-500/50 backdrop-blur-sm transition-opacity dark:bg-gray-900/60" 
            aria-hidden="true"
            @click="showConfirm = false"
        ></div>

        <!-- Trick browser to center modal -->
        <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

        <!-- Modal Card -->
        <div 
            x-show="showConfirm"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="inline-block transform overflow-hidden rounded-2xl bg-white text-left align-middle shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg dark:bg-gray-950 border border-gray-100 dark:border-gray-800"
        >
            <div class="p-6">
                <div class="sm:flex sm:items-start">
                    <!-- Dynamic Theme Icon -->
                    <div 
                        :class="decision === 'approved' ? 'bg-emerald-50 dark:bg-emerald-950/30' : 'bg-amber-50 dark:bg-amber-950/30'"
                        class="mx-auto flex h-12 w-12 shrink-0 items-center justify-center rounded-full sm:mx-0 sm:h-10 sm:w-10"
                    >
                        <!-- Approve Icon -->
                        <svg 
                            x-show="decision === 'approved'" 
                            class="h-6 w-6 text-emerald-600 dark:text-emerald-400" 
                            width="24" 
                            height="24" 
                            style="width: 24px; height: 24px;"
                            fill="none" 
                            viewBox="0 0 24 24" 
                            stroke-width="2" 
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <!-- Revision Icon -->
                        <svg 
                            x-show="decision !== 'approved'" 
                            class="h-6 w-6 text-amber-600 dark:text-amber-400" 
                            width="24" 
                            height="24" 
                            style="width: 24px; height: 24px;"
                            fill="none" 
                            viewBox="0 0 24 24" 
                            stroke-width="2" 
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>

                    <!-- Content -->
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 
                            class="text-base font-bold text-gray-900 dark:text-white" 
                            id="modal-title"
                            x-text="decision === 'approved' ? 'Confirm Document Approval' : 'Confirm Revision Request'"
                        ></h3>
                        <div class="mt-2">
                            <p 
                                class="text-sm text-gray-500 dark:text-gray-400"
                                x-text="decision === 'approved' 
                                    ? 'Are you sure you want to approve this document? This will record your approval and notify the uploader.' 
                                    : 'Are you sure you want to request a revision? The uploader will be notified to upload a new version based on your notes.'"
                            ></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="bg-gray-50/50 px-6 py-4 flex flex-row-reverse gap-3 dark:bg-gray-900/50 border-t border-gray-100 dark:border-gray-800">
                <!-- Submit button -->
                <button 
                    type="button" 
                    @click="
                        confirmed = true; 
                        showConfirm = false; 
                        $nextTick(() => { 
                            $el.closest('form').querySelector('button[type=submit]').click(); 
                        });
                    "
                    :class="decision === 'approved' 
                        ? 'bg-emerald-600 hover:bg-emerald-500 focus:ring-emerald-500 dark:bg-emerald-500 dark:hover:bg-emerald-400' 
                        : 'bg-amber-600 hover:bg-amber-500 focus:ring-amber-500 dark:bg-amber-500 dark:hover:bg-amber-400'"
                    class="inline-flex w-full justify-center rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 sm:ml-3 sm:w-auto transition-all"
                >
                    Yes, Proceed
                </button>
                <!-- Cancel button -->
                <button 
                    type="button" 
                    @click="showConfirm = false" 
                    class="inline-flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 sm:mt-0 sm:w-auto dark:bg-gray-850 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-800 transition-all"
                >
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>
