<?php

// ABOUTME: Shared wiring for the category appearance fields: the color swatch picker and the icon picker.
// ABOUTME: Color reuses the subscription form's tile_color swatch block; icon renders a Lucide SVG per choice.

declare(strict_types=1);

namespace App\Form\Category;

use App\Enum\CategoryIcon;
use App\Enum\TileColor;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;

trait CategoryAppearanceFieldsTrait
{
    /**
     * Add the color swatch picker (the same one the subscription form uses) and the icon picker.
     *
     * @template TData
     *
     * @param FormBuilderInterface<TData> $builder
     */
    private function addAppearanceFields(FormBuilderInterface $builder): void
    {
        $builder
            ->add(child: 'color', type: EnumType::class, options: [
                'class' => TileColor::class,
                'label' => 'Color',
                'expanded' => true,
                'choice_label' => static fn (TileColor $color): string => $color->label(),
                'choice_attr' => static fn (TileColor $color): array => ['data-gradient' => $color->gradientClasses()],
                'block_prefix' => 'tile_color',
            ])
            ->add(child: 'icon', type: EnumType::class, options: [
                'class' => CategoryIcon::class,
                'label' => 'Icon',
                'expanded' => true,
                'choice_label' => static fn (CategoryIcon $icon): string => $icon->label(),
                'choice_attr' => static fn (CategoryIcon $icon): array => ['data-icon' => $icon->iconName()],
                'block_prefix' => 'category_icon',
            ])
        ;
    }
}
