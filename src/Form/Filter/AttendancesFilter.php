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

class AttendancesFilter extends AbstractFilter
{
    public function __construct(#[TaggedIterator('ferienpass_admin.filter.attendance', indexAttribute: 'key')] iterable $filterTypes)
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
            'date' => fn (QueryBuilder $qb) => $qb->addOrderBy('d.begin', 'ASC'),
            'name' => fn (QueryBuilder $qb) => $qb->addOrderBy('o.name', 'ASC'),
        ];
    }
}
