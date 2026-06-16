<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Illuminate\Database\Eloquent\Model;

class LocalSvgAvatarProvider implements AvatarProvider
{
    /**
     * Get the avatar URL (as a data URI SVG) for the given model.
     */
    public function get(Model $record): string
    {
        $name = $record->getAttribute('name') ?? $record->getAttribute('email') ?? 'User';

        // Extract initials (up to 2 characters)
        $words = preg_split('/\s+/', trim($name));
        $initials = '';
        if (count($words) >= 2) {
            $initials = mb_substr($words[0], 0, 1).mb_substr($words[count($words) - 1], 0, 1);
        } else {
            $initials = mb_substr($name, 0, 2);
        }
        $initials = mb_strtoupper($initials);

        // Premium background colors palette
        $colors = [
            '#0f172a', // slate-900
            '#1e293b', // slate-800
            '#1e1b4b', // indigo-950
            '#0f172a', // zinc-950
            '#3b0764', // purple-950
            '#4c0519', // rose-950
            '#022c22', // emerald-950
            '#172554', // blue-950
        ];

        // Pick background color deterministically based on name hash
        $colorIndex = abs(crc32($name)) % count($colors);
        $backgroundColor = $colors[$colorIndex];
        $textColor = '#ffffff';

        // Generate clean and modern SVG code
        $svg = '<?xml version="1.0" encoding="utf-8"?>'.
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100">'.
            '<rect width="100" height="100" fill="'.$backgroundColor.'" />'.
            '<text x="50%" y="50%" font-family="system-ui, -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen, Ubuntu, Cantarell, \'Open Sans\', \'Helvetica Neue\', sans-serif" font-size="40" font-weight="600" fill="'.$textColor.'" text-anchor="middle" dominant-baseline="central">'.htmlspecialchars($initials).'</text>'.
            '</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
