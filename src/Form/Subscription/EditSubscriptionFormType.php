<?php

// ABOUTME: Symfony form type for editing subscriptions with validation rules.
// ABOUTME: Maps form fields to UpdateSubscriptionDto with name validation constraints.

declare(strict_types=1);

namespace App\Form\Subscription;

use App\Dto\Subscription\UpdateSubscriptionDto;
use App\Entity\Category;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\TileColor;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<UpdateSubscriptionDto>
 */
final class EditSubscriptionFormType extends AbstractType
{
    use MoneyCostFieldTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(child: 'category', type: EntityType::class, options: [
                'class' => Category::class,
                'label' => 'Category',
                'choice_label' => 'name',
            ])
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
            ])
            ->add(child: 'paymentPeriodCount', type: NumberType::class, options: [
                'label' => 'Payment Period Count',
            ])
        ;

        // Cost is entered in major units and scales by the chosen currency's fraction digits.
        $this->addCostField($builder);

        $builder
            ->add(child: 'currency', type: EnumType::class, options: [
                'class' => Currency::class,
                'label' => 'Currency',
                'choice_label' => static fn (Currency $currency): string => $currency->value . ' - ' . $currency->label(),
                // Locked once a payment exists: a disabled field is never bound from the request,
                // so the subscription keeps its currency regardless of what is submitted.
                'disabled' => true === $options['lock_currency'],
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
            ->add(child: 'color', type: EnumType::class, options: [
                'class' => TileColor::class,
                'label' => 'Color',
                'expanded' => true,
                'choice_label' => static fn (TileColor $color): string => $color->label(),
                'choice_attr' => static fn (TileColor $color): array => ['data-gradient' => $color->gradientClasses()],
                'block_prefix' => 'tile_color',
            ])
        ;

        // Resuming automated generation is only meaningful for a subscription the user has taken
        // over manually; the Next Renewal Date above doubles as the resume anchor.
        if (true === $options['offer_restart']) {
            $builder->add(child: 'restartPaymentGeneration', type: CheckboxType::class, options: [
                'label' => 'Restart automatic payments?',
                'required' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(defaults: [
            'data_class' => UpdateSubscriptionDto::class,
            'offer_restart' => false,
            'lock_currency' => false,
        ]);
        $resolver->setAllowedTypes(option: 'offer_restart', allowedTypes: 'bool');
        $resolver->setAllowedTypes(option: 'lock_currency', allowedTypes: 'bool');
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'edit_subscription';
    }
}
