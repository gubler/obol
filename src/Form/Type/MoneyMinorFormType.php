<?php

// ABOUTME: A money input bound to a minor-unit integer model, entered/displayed in major units.
// ABOUTME: The `fraction_digits` option scales the amount for the field's currency (2 for USD, 0 for JPY).

declare(strict_types=1);

namespace App\Form\Type;

use App\Service\Money\MoneyParser;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<int>
 */
final class MoneyMinorFormType extends AbstractType
{
    public function __construct(private readonly MoneyParser $parser)
    {
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array{fraction_digits: int} $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new MoneyMinorTransformer($this->parser, $options['fraction_digits']));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(defaults: [
            'fraction_digits' => 2,
            'invalid_message' => 'Please enter a valid amount.',
        ]);
        $resolver->setAllowedTypes(option: 'fraction_digits', allowedTypes: 'int');
    }

    #[\Override]
    public function getParent(): string
    {
        return TextType::class;
    }
}
