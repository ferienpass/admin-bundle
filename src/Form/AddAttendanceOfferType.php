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
use Ferienpass\CoreBundle\Entity\Offer\OfferInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

#[AsEntityAutocompleteField(alias: 'neue-anmeldung-angebote', route: 'ux_entity_autocomplete_admin')]
class AddAttendanceOfferType extends AbstractType
{
    public function __construct(#[Autowire(param: 'ferienpass.model.offer.class')] private readonly string $offerEntityClass)
    {
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'class' => $this->offerEntityClass,
            'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('o')
                ->leftJoin('o.dates', 'dates')
                ->where('dates.begin >= CURRENT_TIMESTAMP()')
                ->andWhere('o.onlineApplication = 1')
                ->orderBy('o.name'),
            'choice_label' => function (OfferInterface $choice): TranslatableMessage|string {
                return sprintf('%s (%s)', $choice->getName(), $choice->getEdition()?->getName());
            },
            'autocomplete' => true,
            'placeholder' => '-',
            'searchable_fields' => ['name', 'id', 'variantBase.id'],
            'security' => 'ROLE_ADMIN',
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
