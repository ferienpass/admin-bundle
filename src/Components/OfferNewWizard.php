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

namespace Ferienpass\AdminBundle\Components;

use Doctrine\ORM\QueryBuilder;
use Ferienpass\CoreBundle\Entity\Edition;
use Ferienpass\CoreBundle\Entity\User;
use Ferienpass\CoreBundle\Repository\OfferRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsLiveComponent(route: 'live_component_admin')]
class OfferNewWizard extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp]
    public Edition $edition;

    public ?string $action = null;

    public function __construct(private readonly OfferRepositoryInterface $offers)
    {
    }

    #[LiveListener('action')]
    public function new(#[LiveArg] string $action): void
    {
        $this->action = $action;
    }

    #[ExposeInTemplate]
    public function offers(): array
    {
        $qb = $this->prepareQueryBuilder($this->action);

        $qb->setMaxResults(25);

        return $qb->getQuery()->getResult();
    }

    #[ExposeInTemplate]
    public function canCopy(): bool
    {
        $qb = $this->prepareQueryBuilder('copy');
        $qb->select('COUNT(o)');

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }

    #[ExposeInTemplate]
    public function canCreateVariant(): bool
    {
        $qb = $this->prepareQueryBuilder('createVariant');
        $qb->select('COUNT(o)');

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }

    private function prepareQueryBuilder(?string $action): QueryBuilder
    {
        $qb = $this->offers->createQueryBuilder('o');

        if ('createVariant' === $action) {
            $qb->andWhere($qb->expr()->eq('o.edition', ':edition'));
            $qb->setParameter('edition', $this->edition);
        }

        if ('copy' === $action) {
            $qb->andWhere($qb->expr()->neq('o.edition', ':edition'));
            $qb->setParameter('edition', $this->edition);
        }

        if (!$this->isGranted('ROLE_ADMIN')) {
            $user = $this->getUser();
            $qb->innerJoin('o.hosts', 'hosts')->andWhere('hosts IN (:hosts)')->setParameter('hosts', $user instanceof User ? $user->getHosts() : []);
        }

        $qb->andWhere('o.variantBase IS NULL');

        $qb->orderBy('o.edition', 'DESC');
        $qb->addOrderBy('o.name', 'ASC');

        return $qb;
    }
}
