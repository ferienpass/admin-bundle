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

namespace Ferienpass\AdminBundle\Form\Filter\Offer;

use Doctrine\ORM\QueryBuilder;
use Ferienpass\AdminBundle\Form\Filter\AbstractFilterType;
use Ferienpass\CoreBundle\Entity\Offer\OfferInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

class StatusFilter extends AbstractFilterType
{
    public function __construct(private readonly Security $security)
    {
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function shallDisplay(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => [OfferInterface::STATE_DRAFT, OfferInterface::STATE_COMPLETED, OfferInterface::STATE_REVIEWED, OfferInterface::STATE_PUBLISHED, OfferInterface::STATE_UNPUBLISHED, OfferInterface::STATE_CANCELLED],
            'choice_label' => function (string $choice): TranslatableMessage {
                return new TranslatableMessage('offers.status.'.$choice, [], 'admin');
            },
            'placeholder' => '-',
            'expanded' => true,
            'multiple' => false,
        ]);
    }

    public function apply(QueryBuilder $qb, FormInterface $form): void
    {
        if ($form->isEmpty()) {
            return;
        }

        $k = $form->getName();
        $v = $form->getData();

        $qb
            ->andWhere('i.state = :q_'.$k)
            ->setParameter('q_'.$k, $v)
        ;
    }

    protected function getHumanReadableValue(FormInterface $form): null|string|TranslatableInterface
    {
        return new TranslatableMessage('offers.status.'.$form->getData(), domain: 'admin');
    }
}
