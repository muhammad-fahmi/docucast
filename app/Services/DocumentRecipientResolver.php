<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;

class DocumentRecipientResolver
{
    public function syncRecipientsFromState(Document $document, array $state): array
    {
        $selectionType = $state['recipient_selection_type'] ?? 'individual';

        if ($selectionType === 'individual') {
            $recipientIds = array_values(array_unique(array_map('intval', $state['recipient_user_ids'] ?? [])));
            $syncResult = $document->recipients()->sync($recipientIds);

            return $syncResult['attached'] ?? [];
        }

        $divisionIds = array_filter(array_map('intval', $state['recipient_division_ids'] ?? []));

        if (empty($divisionIds)) {
            $syncResult = $document->recipients()->sync([]);

            return $syncResult['attached'] ?? [];
        }

        $recipientIds = User::query()
            ->whereIn('division_id', $divisionIds)
            ->whereHas('roles', fn ($query) => $query->where('name', 'recipient'))
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $syncResult = $document->recipients()->sync($recipientIds);

        return $syncResult['attached'] ?? [];
    }
}
