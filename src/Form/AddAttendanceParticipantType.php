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

use Doctrine\ORM\EntityRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

#[AsEntityAutocompleteField(alias: 'neue-anmeldung-teilnehmende', route: 'ux_entity_autocomplete_admin')]
class AddAttendanceParticipantType extends AbstractType
{
    public function __construct(#[Autowire(param: 'ferienpass.model.participant.class')] private readonly string $participantEntityClass)
    {
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'class' => $this->participantEntityClass,
            'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('p')->orderBy('p.lastname'),
            'choice_label' => 'name',
            'autocomplete' => true,
            'placeholder' => '-',
            'searchable_fields' => ['firstname', 'lastname', 'user.lastname', 'user.email', 'email'],
            'security' => 'ROLE_ADMIN',
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
