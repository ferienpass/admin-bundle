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

namespace Ferienpass\AdminBundle\Form\CompoundType;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\LiveComponent\Form\Type\LiveCollectionType;

class ApplicationExtraFieldsType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'entry_type' => ApplicationExtraFieldType::class,
            'delete_empty' => fn ($v) => empty($v['question'] ?? ''),
            'label' => false,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
        ]);
    }

    public function getParent(): string
    {
        return LiveCollectionType::class;
    }
}
