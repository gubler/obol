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
     * Tailwind classes for a top-to-bottom gradient (lighter `from` at the top, darker `to` at the bottom
     * where the tile text sits). White text is always legible on these swatches. The literals must stay in
     * this file so Tailwind's source scan compiles them.
     */
    public function gradientClasses(): string
    {
        return 'bg-linear-to-b ' . match ($this) {
            self::Red => 'from-red-600 to-red-950',
            self::Magenta => 'from-fuchsia-500 to-fuchsia-950',
            self::Pink => 'from-pink-600 to-pink-950',
            self::Orange => 'from-orange-500 to-orange-900',
            self::Brown => 'from-amber-700 to-amber-950',
            self::Gold => 'from-yellow-500 to-yellow-900',
            self::Lime => 'from-lime-500 to-lime-900',
            self::Green => 'from-green-600 to-green-950',
            self::Emerald => 'from-emerald-800 to-emerald-950',
            self::Teal => 'from-teal-500 to-teal-950',
            self::Cyan => 'from-sky-500 to-sky-950',
            self::Blue => 'from-blue-600 to-blue-950',
            self::Indigo => 'from-indigo-500 to-indigo-950',
            self::Violet => 'from-violet-500 to-violet-950',
            self::Purple => 'from-purple-500 to-purple-950',
            self::Slate => 'from-slate-500 to-slate-800',
            self::Grey => 'from-neutral-500 to-neutral-800',
            self::Charcoal => 'from-stone-800 to-stone-950',
        };
    }

    public function label(): string
    {
        return $this->name;
    }

    public static function random(): self
    {
        $cases = self::cases();

        return $cases[random_int(0, \count($cases) - 1)];
    }
}
