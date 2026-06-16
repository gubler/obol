<?php

// ABOUTME: Symfony form type for creating new subscriptions with validation rules.
// ABOUTME: Maps form fields to CreateSubscriptionDto with name validation constraints.

declare(strict_types=1);

namespace App\Form\Subscription;

use App\Dto\Subscription\CreateSubscriptionDto;
use App\Entity\Category;
use App\Enum\PaymentPeriod;
use App\Enum\TileColor;
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
    use CurrencyFieldTrait;
    use MoneyCostFieldTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // The category picker is only offered when categories exist; without one the subscription is
        // created uncategorized. The controller passes the flag so the form needs no database access.
        if (true === $options['has_categories']) {
            $builder->add(child: 'category', type: EntityType::class, options: [
                'class' => Category::class,
                'label' => 'Category',
                'choice_label' => 'name',
                'placeholder' => 'Uncategorized',
                'required' => false,
            ]);
        }

        $builder
            ->add(child: 'name', type: TextType::class, options: [
                'label' => 'Subscription Name',
                'empty_data' => '',
            ])
            ->add(child: 'nextRenewal', type: DateType::class, options: [
                'label' => 'Next Renewal Date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add(child: 'paymentPeriod', type: EnumType::class, options: [
                'class' => PaymentPeriod::class,
                'label' => 'Payment Period',
                // The singular noun the billing-cycle controller pluralizes against the count.
                'choice_attr' => static fn (PaymentPeriod $period): array => ['data-singular' => ucfirst($period->value)],
            ])
            ->add(child: 'paymentPeriodCount', type: NumberType::class, options: [
                'label' => 'Payment Period Count',
            ])
        ;

        // Cost is entered in major units and scales by the chosen currency's fraction digits.
        $this->addCostField($builder);
        // Currency renders inline with the cost, as "<symbol> <ISO>" choices.
        $this->addCurrencyField($builder);

        $builder
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
            ->add(child: 'color', type: EnumType::class, options: [
                'class' => TileColor::class,
                'label' => 'Color',
                'expanded' => true,
                'choice_label' => static fn (TileColor $color): string => $color->label(),
                'choice_attr' => static fn (TileColor $color): array => ['data-gradient' => $color->gradientClasses()],
                'block_prefix' => 'tile_color',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(defaults: [
            'data_class' => CreateSubscriptionDto::class,
            'has_categories' => true,
        ]);
        $resolver->setAllowedTypes(option: 'has_categories', allowedTypes: 'bool');
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'create_subscription';
    }
}
