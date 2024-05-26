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
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConsentsFilter extends AbstractFilter
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'label_format' => 'consents.filter.%name%',
        ]);
    }

    protected function getSorting(): array
    {
        return [
            'signedAt' => fn (QueryBuilder $qb) => $qb->addOrderBy('i.signedAt', 'DESC'),
        ];
    }
}
