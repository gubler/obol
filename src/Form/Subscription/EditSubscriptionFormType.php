<?php

// ABOUTME: Symfony form type for editing subscriptions with validation rules.
// ABOUTME: Maps form fields to UpdateSubscriptionDto with name validation constraints.

declare(strict_types=1);

namespace App\Form\Subscription;

use App\Dto\Subscription\UpdateSubscriptionDto;
use App\Entity\Category;
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
    use CurrencyFieldTrait;
    use MoneyCostFieldTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // The category picker is only offered when categories exist; clearing it (or having none)
        // leaves the subscription uncategorized. The controller passes the flag so the form needs no
        // database access.
        if (true === $options['has_categories']) {
            $builder->add(child: 'category', type: EntityType::class, options: [
                'class' => Category::class,
                'label' => 'subscription.form.category',
                'choice_label' => 'name',
                'placeholder' => 'subscription.group.uncategorized',
                'required' => false,
            ]);
        }

        $builder
            ->add(child: 'name', type: TextType::class, options: [
                'label' => 'subscription.form.name',
                'empty_data' => '',
            ])
            ->add(child: 'nextRenewal', type: DateType::class, options: [
                'label' => 'subscription.form.next_renewal',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add(child: 'paymentPeriod', type: EnumType::class, options: [
                'class' => PaymentPeriod::class,
                'label' => 'subscription.form.payment_period',
                'choice_label' => static fn (PaymentPeriod $period): string => $period->label(),
                // The singular noun the billing-cycle controller pluralizes against the count.
                'choice_attr' => static fn (PaymentPeriod $period): array => ['data-singular' => ucfirst($period->value)],
            ])
            ->add(child: 'paymentPeriodCount', type: NumberType::class, options: [
                'label' => 'subscription.form.payment_period_count',
            ])
        ;

        // Cost is entered in major units and scales by the chosen currency's fraction digits.
        $this->addCostField($builder);
        // Currency renders inline with the cost; locked once a payment exists.
        $this->addCurrencyField($builder, true === $options['lock_currency']);

        $builder
            ->add(child: 'description', type: TextareaType::class, options: [
                'label' => 'subscription.form.description',
                'required' => false,
                'empty_data' => '',
            ])
            ->add(child: 'link', type: TextType::class, options: [
                'label' => 'subscription.form.link',
                'required' => false,
                'empty_data' => '',
            ])
            ->add(child: 'logo', type: FileType::class, options: [
                'label' => 'subscription.form.logo',
                'required' => false,
                'empty_data' => '',
            ])
            ->add(child: 'color', type: EnumType::class, options: [
                'class' => TileColor::class,
                'label' => 'subscription.form.color',
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
                'label' => 'subscription.form.restart_payments',
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
            'has_categories' => true,
        ]);
        $resolver->setAllowedTypes(option: 'offer_restart', allowedTypes: 'bool');
        $resolver->setAllowedTypes(option: 'lock_currency', allowedTypes: 'bool');
        $resolver->setAllowedTypes(option: 'has_categories', allowedTypes: 'bool');
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'edit_subscription';
    }
}
