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
            $document->recipients()->sync($recipientIds);

            return $recipientIds;
        }

        $divisionId = isset($state['recipient_division_id']) ? (int) $state['recipient_division_id'] : null;

        if ($divisionId === null || $divisionId <= 0) {
            $document->recipients()->sync([]);

            return [];
        }

        $recipientIds = User::query()
            ->where('division_id', $divisionId)
            ->whereHas('roles', fn($query) => $query->where('name', 'recipient'))
            ->pluck('id')
            ->map(static fn($id): int => (int) $id)
            ->all();

        $document->recipients()->sync($recipientIds);

        return $recipientIds;
    }
}
