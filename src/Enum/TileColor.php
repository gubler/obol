<?php

// ABOUTME: Curated palette of subscription tile colors, each mapped to a Tailwind v4 gradient.
// ABOUTME: Append-only - never edit or delete a case (its Tailwind classes and stored rows depend on it).

declare(strict_types=1);

namespace App\Enum;

enum TileColor: string
{
    case Red = 'red';
    case Magenta = 'magenta';
    case Pink = 'pink';
    case Orange = 'orange';
    case Brown = 'brown';
    case Gold = 'gold';
    case Lime = 'lime';
    case Green = 'green';
    case Emerald = 'emerald';
    case Teal = 'teal';
    case Cyan = 'cyan';
    case Blue = 'blue';
    case Indigo = 'indigo';
    case Violet = 'violet';
    case Purple = 'purple';
    case Slate = 'slate';
    case Grey = 'grey';
    case Charcoal = 'charcoal';

    /**
     * Tailwind classes for a top-to-bottom gradient. The vivid `from` tone is held to a `from-45%`
     * stop so the lighter color stays dominant and the darker `to` is compressed into the bottom
     * edge; this keeps small tiles from averaging too dark on a light page and stops the deepest
     * swatches from merging into the dark page. The `from` shade is kept dark enough that the white
     * tile text (cost at the top, name at the bottom) is always legible. The literals must stay in
     * this file so Tailwind's source scan compiles them.
     */
    public function gradientClasses(): string
    {
        return 'bg-linear-to-b ' . match ($this) {
            self::Red => 'from-red-500 from-45% to-red-900',
            self::Magenta => 'from-fuchsia-600 from-45% to-fuchsia-900',
            self::Pink => 'from-pink-600 from-45% to-pink-900',
            self::Orange => 'from-orange-600 from-45% to-orange-900',
            self::Brown => 'from-amber-700 from-45% to-amber-950',
            self::Gold => 'from-yellow-600 from-45% to-yellow-900',
            self::Lime => 'from-lime-600 from-45% to-lime-900',
            self::Green => 'from-green-600 from-45% to-green-900',
            self::Emerald => 'from-emerald-600 from-45% to-emerald-900',
            self::Teal => 'from-teal-600 from-45% to-teal-900',
            self::Cyan => 'from-sky-600 from-45% to-sky-900',
            self::Blue => 'from-blue-600 from-45% to-blue-900',
            self::Indigo => 'from-indigo-500 from-45% to-indigo-900',
            self::Violet => 'from-violet-500 from-45% to-violet-900',
            self::Purple => 'from-purple-500 from-45% to-purple-900',
            self::Slate => 'from-slate-500 from-45% to-slate-800',
            self::Grey => 'from-neutral-500 from-45% to-neutral-800',
            self::Charcoal => 'from-stone-600 from-45% to-stone-900',
        };
    }

    /**
     * The flat, solid base color as a single `bg-*` class: the same bright shade the gradient starts
     * from, without the gradient. Categories render flat (small icons read badly on a gradient). The
     * literals must stay in this file so Tailwind's source scan compiles them.
     */
    public function baseColorClass(): string
    {
        return match ($this) {
            self::Red => 'bg-red-500',
            self::Magenta => 'bg-fuchsia-600',
            self::Pink => 'bg-pink-600',
            self::Orange => 'bg-orange-600',
            self::Brown => 'bg-amber-700',
            self::Gold => 'bg-yellow-600',
            self::Lime => 'bg-lime-600',
            self::Green => 'bg-green-600',
            self::Emerald => 'bg-emerald-600',
            self::Teal => 'bg-teal-600',
            self::Cyan => 'bg-sky-600',
            self::Blue => 'bg-blue-600',
            self::Indigo => 'bg-indigo-500',
            self::Violet => 'bg-violet-500',
            self::Purple => 'bg-purple-500',
            self::Slate => 'bg-slate-500',
            self::Grey => 'bg-neutral-500',
            self::Charcoal => 'bg-stone-600',
        };
    }

    /**
     * The base shade as a hex color, matching `baseColorClass()`. Used for canvas fills (the reports
     * pie) where a CSS class cannot reach - so the wedge matches its flat swatch in the legend.
     */
    public function baseColorHex(): string
    {
        return match ($this) {
            self::Red => '#ef4444',       // red-500
            self::Magenta => '#c026d3',   // fuchsia-600
            self::Pink => '#db2777',      // pink-600
            self::Orange => '#ea580c',    // orange-600
            self::Brown => '#b45309',     // amber-700
            self::Gold => '#ca8a04',      // yellow-600
            self::Lime => '#65a30d',      // lime-600
            self::Green => '#16a34a',     // green-600
            self::Emerald => '#059669',   // emerald-600
            self::Teal => '#0d9488',      // teal-600
            self::Cyan => '#0284c7',      // sky-600
            self::Blue => '#2563eb',      // blue-600
            self::Indigo => '#6366f1',    // indigo-500
            self::Violet => '#8b5cf6',    // violet-500
            self::Purple => '#a855f7',    // purple-500
            self::Slate => '#64748b',     // slate-500
            self::Grey => '#737373',      // neutral-500
            self::Charcoal => '#57534e',  // stone-600
        };
    }

    public function label(): string
    {
        // Translation key resolved in the `messages` catalog; see ADR-0012.
        return 'enum.tile_color.' . $this->value;
    }

    public static function random(): self
    {
        $cases = self::cases();

        return $cases[random_int(0, \count($cases) - 1)];
    }
}
