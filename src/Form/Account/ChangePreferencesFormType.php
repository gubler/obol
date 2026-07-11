<?php

// ABOUTME: Form type for the account preferences edit page (name/currency/language/date-time/timezone).
// ABOUTME: Bound to ChangePreferencesDto; enum dropdowns plus the built-in timezone picker.

declare(strict_types=1);

namespace App\Form\Account;

use App\Dto\Account\ChangePreferencesDto;
use App\Enum\AppLocale;
use App\Enum\Currency;
use App\Enum\DateFormat;
use App\Enum\SavingsDisplay;
use App\Service\DateFormatter;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<ChangePreferencesDto>
 */
final class ChangePreferencesFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly DateFormatter $dateFormatter,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Each date-time-format option carries a live example of now rendered in that style, so the
        // user picks by what dates will actually look like rather than by an abstract name.
        $sample = new \DateTimeImmutable();

        $builder
            ->add('displayName', TextType::class, [
                'label' => 'account.preferences.display_name',
                'required' => false,
                'attr' => ['placeholder' => 'account.preferences.display_name_placeholder'],
            ])
            ->add('displayCurrency', EnumType::class, [
                'class' => Currency::class,
                'label' => 'account.preferences.currency',
                // Each option reads "<symbol> <ISO>" (e.g. "$ USD"), matching the onboarding + subscription forms.
                'choice_label' => static fn (Currency $currency): string => $currency->symbol() . ' ' . $currency->value,
            ])
            ->add('language', EnumType::class, [
                'class' => AppLocale::class,
                'label' => 'account.preferences.language',
                'placeholder' => 'account.preferences.language_placeholder',
                'choice_label' => static fn (AppLocale $locale): string => $locale->label(),
            ])
            ->add('dateFormat', EnumType::class, [
                'class' => DateFormat::class,
                'label' => 'account.preferences.date_time_format',
                // Labels carry a live example (a full datetime), so translate them here rather than
                // letting the choice list auto-translate a plain key.
                'choice_translation_domain' => false,
                'choice_label' => fn (DateFormat $format): string => $this->translator->trans(
                    $format->label(),
                    ['%example%' => $this->dateFormatter->formatDateTime($sample, $format)],
                ),
            ])
            ->add('timezone', TimezoneType::class, [
                'label' => 'account.preferences.timezone',
                // timezone_detect_controller.js refines this to the browser's zone before the user touches it.
                'attr' => ['data-timezone-detect-target' => 'field'],
            ])
            ->add('savingsDisplay', EnumType::class, [
                'class' => SavingsDisplay::class,
                'label' => 'account.preferences.savings_display',
                'choice_label' => static fn (SavingsDisplay $display): string => $display->label(),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ChangePreferencesDto::class,
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'change_preferences';
    }
}
