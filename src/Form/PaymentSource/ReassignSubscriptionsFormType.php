<?php

// ABOUTME: Form type for moving every subscription from one payment source to another.
// ABOUTME: The target picker is owner-scoped and excludes the source being emptied.

declare(strict_types=1);

namespace App\Form\PaymentSource;

use App\Dto\PaymentSource\ReassignSubscriptionsDto;
use App\Entity\PaymentSource;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Uid\Ulid;

/**
 * @extends AbstractType<ReassignSubscriptionsDto>
 */
final class ReassignSubscriptionsFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $ownerId = $options['owner_id'];
        \assert($ownerId instanceof Ulid);
        $currentId = $options['current_id'];
        \assert($currentId instanceof Ulid);

        // Owner-scoped and excluding the source being emptied, so the only valid targets are the user's
        // other sources; a crafted or cross-owner id is simply not a choice and the form rejects it
        // (per-user isolation, ADR-0015). Type-hinting Doctrine's base EntityRepository (rather than the
        // app's concrete repository) keeps the form clear of the handler-layer data-access boundary the
        // arch test guards (ADR-0006/0007); the controller passes the ids so the form needs no lookup.
        $builder->add(child: 'target', type: EntityType::class, options: [
            'class' => PaymentSource::class,
            'label' => 'payment_source.show.move_all_label',
            'choice_label' => 'name',
            'query_builder' => static fn (EntityRepository $repository): QueryBuilder => $repository
                ->createQueryBuilder('payment_source')
                ->andWhere('payment_source.owner = :owner')
                ->setParameter('owner', $ownerId, UlidType::NAME)
                ->andWhere('payment_source.id != :current')
                ->setParameter('current', $currentId, UlidType::NAME)
                ->orderBy('payment_source.name', 'ASC'),
            'placeholder' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ReassignSubscriptionsDto::class]);
        $resolver->setRequired('owner_id');
        $resolver->setRequired('current_id');
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'reassign_subscriptions';
    }
}
