<?php

// ABOUTME: Symfony form type for editing categories with validation rules.
// ABOUTME: Maps form fields to UpdateCategoryDto with name validation constraints.

declare(strict_types=1);

namespace App\Form\Category;

use App\Dto\Category\UpdateCategoryDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<UpdateCategoryDto>
 */
final class EditCategoryFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(child: 'name', type: TextType::class, options: [
                'label' => 'Category Name',
                'empty_data' => '',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(defaults: [
            'data_class' => UpdateCategoryDto::class,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'edit_category';
    }
}
