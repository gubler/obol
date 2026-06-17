<?php

// ABOUTME: Curated, closed set of Lucide icons a category can carry, keyed by the Lucide icon name.
// ABOUTME: Append-only - never edit or delete a case (its value is persisted on category rows).

declare(strict_types=1);

namespace App\Enum;

enum CategoryIcon: string
{
    case Tag = 'tag';
    case Tv = 'tv';
    case Film = 'film';
    case Music = 'music';
    case Headphones = 'headphones';
    case Gamepad2 = 'gamepad-2';
    case BookOpen = 'book-open';
    case Newspaper = 'newspaper';
    case GraduationCap = 'graduation-cap';
    case Cloud = 'cloud';
    case Server = 'server';
    case Code = 'code';
    case Shield = 'shield';
    case Wifi = 'wifi';
    case Zap = 'zap';
    case Dumbbell = 'dumbbell';
    case HeartPulse = 'heart-pulse';
    case Briefcase = 'briefcase';
    case ShoppingCart = 'shopping-cart';
    case Utensils = 'utensils';
    case Coffee = 'coffee';
    case Car = 'car';
    case Plane = 'plane';
    case House = 'house';
    case Phone = 'phone';
    case Mail = 'mail';
    case Camera = 'camera';
    case CreditCard = 'credit-card';
    case Gift = 'gift';
    case Package = 'package';
    case Globe = 'globe';

    /**
     * The ux-icons reference for this icon, e.g. "lucide:gamepad-2". The SVGs are bundled under
     * `assets/icons/lucide/` and served via AssetMapper, so rendering needs no network access.
     */
    public function iconName(): string
    {
        return 'lucide:' . $this->value;
    }

    /**
     * A human label (for the picker's accessible name), derived from the Lucide name -
     * e.g. "book-open" -> "Book Open".
     */
    public function label(): string
    {
        return ucwords(str_replace('-', ' ', $this->value));
    }

    public static function random(): self
    {
        $cases = self::cases();

        return $cases[random_int(0, \count($cases) - 1)];
    }
}
