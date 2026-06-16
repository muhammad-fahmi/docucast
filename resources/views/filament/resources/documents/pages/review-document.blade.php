<x-filament-panels::page>
    <style>
        :root {
            --spacing-gr-xs: 8px;       /* 0.5rem - base / tight space */
            --spacing-gr-sm: 13px;      /* 0.8125rem - list item padding / minor gap */
            --spacing-gr-md: 21px;      /* 1.3125rem - card padding / major gap */
            --spacing-gr-lg: 34px;      /* 2.125rem - section spacing / wrapper margin */
            --spacing-gr-xl: 55px;      /* 3.4375rem - generous page card padding */
        }
        .review-page-card {
            padding: var(--spacing-gr-md);
            margin-top: var(--spacing-gr-md);
            margin-bottom: var(--spacing-gr-lg);
            border-radius: 0.75rem;
            border: 1px solid rgb(229, 231, 235);
            background-color: rgb(255, 255, 255);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }
        .dark .review-page-card {
            border-color: rgba(255, 255, 255, 0.1);
            background-color: var(--gray-900);
            box-shadow: none;
        }
        @media (min-width: 1024px) {
            .review-page-card {
                padding: var(--spacing-gr-xl);
                margin-top: var(--spacing-gr-lg);
                margin-bottom: var(--spacing-gr-xl);
            }
        }
        .review-desktop-preview,
        .review-non-pdf-preview {
            display: flex !important;
            flex-direction: column;
            border-radius: 0.75rem;
            border: 1px solid rgb(229, 231, 235);
            background-color: rgb(255, 255, 255);
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px -1px rgba(0, 0, 0, 0.03);
            overflow: hidden;
        }
        .dark .review-desktop-preview,
        .dark .review-non-pdf-preview {
            border-color: rgba(255, 255, 255, 0.1);
            background-color: var(--gray-955); /* fallback */
            background-color: var(--gray-950);
            box-shadow: none;
        }
        .review-mobile-preview {
            display: none !important;
            text-align: center;
            padding: 1.5rem;
            border-radius: 0.75rem;
            border: 1px solid rgb(229, 231, 235);
            background-color: rgb(249, 250, 251);
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px -1px rgba(0, 0, 0, 0.03);
            max-width: 28rem;
            margin-left: auto;
            margin-right: auto;
        }
        .dark .review-mobile-preview {
            border-color: rgba(255, 255, 255, 0.1);
            background-color: var(--gray-950);
            box-shadow: none;
        }
        .review-mobile-preview > * + * {
            margin-top: 1rem;
        }
        @media (max-width: 767px) {
            .review-desktop-preview {
                display: none !important;
            }
            .review-mobile-preview {
                display: block !important;
            }
        }
        .review-preview-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgb(229, 231, 235);
            background-color: rgba(249, 250, 251, 0.5);
            padding: var(--spacing-gr-sm) var(--spacing-gr-md);
        }
        .dark .review-preview-header {
            border-bottom-color: rgba(255, 255, 255, 0.1);
            background-color: rgba(24, 24, 27, 0.5);
        }
        .review-non-pdf-content {
            border-bottom-left-radius: 0.75rem;
            border-bottom-right-radius: 0.75rem;
            background-color: color-mix(in srgb, var(--primary-50) 10%, transparent);
            padding: var(--spacing-gr-md);
            font-size: 0.875rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            flex: 1 1 0%;
        }
        .dark .review-non-pdf-content {
            background-color: color-mix(in srgb, var(--primary-950) 5%, transparent);
        }
        .review-non-pdf-content > * + * {
            margin-top: 1rem;
        }
        .review-info-card {
            border-radius: 0.75rem;
            border: 1px solid rgb(229, 231, 235);
            background-color: rgba(249, 250, 251, 0.5);
            padding: var(--spacing-gr-md);
        }
        .dark .review-info-card {
            border-color: rgba(255, 255, 255, 0.1);
            background-color: rgba(24, 24, 27, 0.3);
        }
        .review-version-list {
            border: 1px solid rgb(229, 231, 235);
            background-color: rgb(255, 255, 255);
            border-radius: 0.75rem;
            overflow: hidden;
        }
        .dark .review-version-list {
            border-color: rgba(255, 255, 255, 0.1);
            background-color: var(--gray-950);
        }
        .review-version-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--spacing-gr-sm) var(--spacing-gr-md);
            font-size: 0.75rem;
            border-bottom: 1px solid rgb(229, 231, 235);
            transition: background-color 0.2s;
        }
        .review-version-item:last-child {
            border-bottom: none;
        }
        .review-version-item:hover {
            background-color: rgba(249, 250, 251, 0.5);
        }
        .dark .review-version-item {
            border-bottom-color: rgba(255, 255, 255, 0.1);
        }
        .dark .review-version-item:hover {
            background-color: rgba(255, 255, 255, 0.02);
        }
        .review-download-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            border-radius: 0.5rem;
            border: 1px solid rgb(229, 231, 235);
            background-color: rgb(255, 255, 255);
            padding: 0.375rem 0.625rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: rgb(55, 65, 81);
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            transition: background-color 0.2s, border-color 0.2s;
            text-decoration: none;
        }
        .review-download-btn svg {
            transform: translateY(-0.5px);
        }
        .review-download-btn:hover {
            background-color: rgb(249, 250, 251);
        }
        .dark .review-download-btn {
            border-color: rgba(255, 255, 255, 0.1);
            background-color: var(--gray-900);
            color: rgb(209, 213, 219);
        }
        .dark .review-download-btn:hover {
            background-color: var(--gray-800);
        }
        .review-version-label-container {
            flex-shrink: 0;
            padding-right: var(--spacing-gr-sm);
            margin-right: var(--spacing-gr-sm);
            border-right: 1px solid rgb(229, 231, 235);
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 2.75rem;
        }
        .dark .review-version-label-container {
            border-right-color: rgba(255, 255, 255, 0.1);
        }
        .review-version-label-text {
            font-size: 1rem !important;
            font-weight: 700;
            color: var(--primary-600);
        }
        .dark .review-version-label-text {
            color: var(--primary-400);
        }
        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            border-radius: 0.5rem;
            background-color: var(--primary-600) !important;
            color: #ffffff !important;
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            transition: background-color 0.2s, border-color 0.2s;
            text-decoration: none;
            border: 1px solid transparent;
        }
        .btn-primary svg {
            transform: translateY(-0.5px);
        }
        .btn-primary:hover {
            background-color: var(--primary-500) !important;
        }
        .btn-mobile-primary {
            display: inline-flex;
            width: 100%;
            align-items: center;
            justify-content: center;
            gap: 0.375rem;
            border-radius: 0.5rem;
            background-color: var(--primary-600) !important;
            color: #ffffff !important;
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            line-height: 1;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            transition: background-color 0.2s;
            text-decoration: none;
        }
        .btn-mobile-primary svg {
            transform: translateY(-0.5px);
        }
        .btn-mobile-primary:hover {
            background-color: var(--primary-500) !important;
        }
        .review-page-card form {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-gr-md);
        }
        .review-form-actions {
            display: flex;
            gap: var(--spacing-gr-sm);
            justify-content: flex-end;
            padding-top: var(--spacing-gr-md);
            margin-top: var(--spacing-gr-md);
            border-top: 1px solid rgb(229, 231, 235);
        }
        .dark .review-form-actions {
            border-top-color: rgba(255, 255, 255, 0.1);
        }
    </style>
    <div class="review-page-card">
        <form wire:submit="submitReview">
            {{ $this->form }}
 
            <div class="review-form-actions">
                <x-filament::button tag="a" href="{{ static::getResource()::getUrl('index') }}" color="gray">
                    Cancel
                </x-filament::button>
                <x-filament::button type="submit" color="primary">
                    Submit Review
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
