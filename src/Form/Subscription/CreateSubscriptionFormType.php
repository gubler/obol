<?php

// ABOUTME: Symfony form type for creating new subscriptions with validation rules.
// ABOUTME: Maps form fields to CreateSubscriptionDto with name validation constraints.

declare(strict_types=1);

namespace App\Form\Subscription;

use App\Dto\Subscription\CreateSubscriptionDto;
use App\Entity\Category;
use App\Enum\PaymentPeriod;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<CreateSubscriptionDto>
 */
final class CreateSubscriptionFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(child: 'category', type: EntityType::class, options: [
                'class' => Category::class,
                'label' => 'Category',
                'choice_label' => 'name',
                'placeholder' => 'Select a category',
            ])
            ->add(child: 'name', type: TextType::class, options: [
                'label' => 'Subscription Name',
                'empty_data' => '',
            ])
            ->add(child: 'lastPaidDate', type: DateType::class, options: [
                'label' => 'Last Paid Date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add(child: 'paymentPeriod', type: EnumType::class, options: [
                'class' => PaymentPeriod::class,
                'label' => 'Payment Period',
            ])
            ->add(child: 'paymentPeriodCount', type: NumberType::class, options: [
                'label' => 'Payment Period Count',
            ])
            ->add(child: 'cost', type: NumberType::class, options: [
                'label' => 'Cost (USD)',
            ])
            ->add(child: 'description', type: TextareaType::class, options: [
                'label' => 'Description',
                'required' => false,
                'empty_data' => '',
            ])
            ->add(child: 'link', type: TextType::class, options: [
                'label' => 'Link',
                'required' => false,
                'empty_data' => '',
            ])
            ->add(child: 'logo', type: FileType::class, options: [
                'label' => 'Logo',
                'required' => false,
                'empty_data' => '',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(defaults: [
            'data_class' => CreateSubscriptionDto::class,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'create_subscription';
    }
}
