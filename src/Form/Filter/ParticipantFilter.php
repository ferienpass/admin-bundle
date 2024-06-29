<?php

declare(strict_types=1);

/*
 * This file is part of the Ferienpass package.
 *
 * (c) Richard Henkenjohann <richard@ferienpass.online>
 *
 * For more information visit the project website <https://ferienpass.online>
 * or the documentation under <https://docs.ferienpass.online>.
 */

namespace Ferienpass\AdminBundle\Form\Filter;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParticipantFilter extends AbstractFilter
{
    public function __construct(#[TaggedIterator('ferienpass_admin.filter.participant', indexAttribute: 'key')] iterable $filterTypes)
    {
        $this->filterTypes = $filterTypes instanceof \Traversable ? iterator_to_array($filterTypes) : $filterTypes;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'label_format' => 'participants.filter.%name%',
        ]);
    }

    protected function getSorting(): array
    {
        return [
            'name' => fn (QueryBuilder $qb) => $qb->addOrderBy('i.lastname', 'ASC'),
            'createdAt' => fn (QueryBuilder $qb) => $qb->addOrderBy('i.createdAt', 'DESC'),
            // 'numberAttendances' => fn (QueryBuilder $qb) => $qb->addSelect('COUNT(a) AS HIDDEN countAttendances')->leftJoin('i.attendances', 'a')->addGroupBy('i')->addGroupBy('a')->addOrderBy('countAttendances', 'DESC'),
        ];
    }
}
