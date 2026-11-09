<?php

namespace App\Support;

/**
 * The tagging badge used in Transactions
 */
class TagBadge
{
    /**
     * @return array{class: string, style: string}
     */
    public static function attributes(?string $color): array
    {
        $backgroundColor = preg_match('/^#[a-fA-F0-9]{6}$/', $color ?? '') === 1 ? $color : '#e5e7eb';
        $linearChannels = array_map(function (int $channel): float {
            $normalizedChannel = $channel / 255;

            return $normalizedChannel <= 0.04045 ? $normalizedChannel / 12.92 : (($normalizedChannel + 0.055) / 1.055) ** 2.4;
        }, sscanf($backgroundColor, '#%02x%02x%02x'));

        $luminance = 0.2126 * $linearChannels[0] + 0.7152 * $linearChannels[1] + 0.0722 * $linearChannels[2];
        $blackContrast = ($luminance + 0.05) / 0.05;
        $whiteContrast = 1.05 / ($luminance + 0.05);
        $textColor = $blackContrast >= $whiteContrast ? '#000000' : '#ffffff';

        return [
            'class' => 'tag-name-badge',
            'style' => "--tag-background-color: {$backgroundColor}; --tag-text-color: {$textColor};",
        ];
    }
}
