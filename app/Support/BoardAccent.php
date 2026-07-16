<?php

namespace App\Support;

/**
 * ワークフローごとのボード配色。クラス名は完全なリテラル文字列として保持する必要がある
 * （TailwindのJITスキャナは動的に組み立てた文字列を検出できないため）。
 */
class BoardAccent
{
    /**
     * @return array<string, string>
     */
    public static function classes(string $accent): array
    {
        return match ($accent) {
            'orange' => [
                'icon' => 'text-orange-600',
                'button' => 'bg-orange-600 hover:bg-orange-700',
                'ring' => 'focus:ring-orange-500',
                'badge_soft_bg' => 'bg-orange-50',
                'badge_soft_text' => 'text-orange-700',
                'badge_soft_border' => 'border-orange-100',
                'badge_solid_bg' => 'bg-orange-100',
                'badge_solid_text' => 'text-orange-800',
                'text' => 'text-orange-600',
                'dot' => 'bg-orange-500',
                'nav_active' => 'bg-orange-50 text-orange-700',
                'link' => 'text-orange-600 hover:text-orange-800',
                'drop_zone' => 'bg-orange-50 border-orange-300 border-dashed border-2',
            ],
            default => [
                'icon' => 'text-blue-600',
                'button' => 'bg-blue-600 hover:bg-blue-700',
                'ring' => 'focus:ring-blue-500',
                'badge_soft_bg' => 'bg-blue-50',
                'badge_soft_text' => 'text-blue-700',
                'badge_soft_border' => 'border-blue-100',
                'badge_solid_bg' => 'bg-blue-100',
                'badge_solid_text' => 'text-blue-800',
                'text' => 'text-blue-600',
                'dot' => 'bg-blue-500',
                'nav_active' => 'bg-blue-50 text-blue-700',
                'link' => 'text-blue-600 hover:text-blue-800',
                'drop_zone' => 'bg-teal-50 border-teal-300 border-dashed border-2',
            ],
        };
    }
}
