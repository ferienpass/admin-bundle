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

namespace Ferienpass\AdminBundle\Form;

use Ferienpass\AdminBundle\Form\CompoundType\EditionTaskType;
use Ferienpass\CoreBundle\Entity\Edition;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EditEditionType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Edition::class,
            'label_format' => 'editions.label.%name%',
            'translation_domain' => 'admin',
            'required' => false,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, ['fieldset_group' => 'base', 'width' => '1/2'])
            ->add('alias', null, ['fieldset_group' => 'base', 'width' => '1/2', 'help' => 'editions.help.alias'])
            ->add('tasks', CollectionType::class, [
                'fieldset_group' => 'tasks',
                'entry_type' => EditionTaskType::class,
                'entry_options' => ['label' => false],
                'label' => false,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Daten speichern',
            ])
        ;
    }
}
