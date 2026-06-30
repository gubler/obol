<?php

// ABOUTME: Symfony form type for creating and editing payment sources (name, comment, color).
// ABOUTME: One form for both; the controller passes the data_class (create vs update DTO) per use.

declare(strict_types=1);

namespace App\Form\PaymentSource;

use App\Enum\TileColor;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<object>
 */
final class PaymentSourceFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(child: 'name', type: TextType::class, options: [
                'label' => 'payment_source.form.name',
                'required' => true,
                'empty_data' => '',
            ])
            ->add(child: 'comment', type: TextareaType::class, options: [
                'label' => 'payment_source.form.comment',
                'required' => false,
                'empty_data' => '',
            ])
            ->add(child: 'color', type: EnumType::class, options: [
                'class' => TileColor::class,
                'label' => 'payment_source.form.color',
                'expanded' => true,
                'choice_label' => static fn (TileColor $color): string => $color->label(),
                'choice_attr' => static fn (TileColor $color): array => ['data-gradient' => $color->gradientClasses()],
                // Reuses the subscription form's swatch block.
                'block_prefix' => 'tile_color',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // The controller picks the DTO (create vs update); both carry name/comment/color.
        $resolver->setRequired('data_class');
        $resolver->setAllowedTypes(option: 'data_class', allowedTypes: 'string');
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'payment_source';
    }
}
