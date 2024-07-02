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

use Contao\StringUtil;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Ferienpass\CoreBundle\Entity\Attendance;
use Ferienpass\CoreBundle\Entity\Edition;
use Ferienpass\CoreBundle\Entity\Offer\OfferInterface;
use Ferienpass\CoreBundle\Entity\Participant\ParticipantInterface;
use Ferienpass\CoreBundle\Notification\MailingNotification;
use Ferienpass\CoreBundle\Notifier\Notifier;
use Ferienpass\CoreBundle\Repository\EditionRepository;
use Ferienpass\CoreBundle\Repository\HostRepository;
use Ferienpass\CoreBundle\Repository\OfferRepositoryInterface;
use Ferienpass\CoreBundle\Repository\ParticipantRepositoryInterface;
use Ferienpass\CoreBundle\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\Metadata\UrlMapping;
use Symfony\UX\LiveComponent\ValidatableComponentTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;
use Symfony\UX\TwigComponent\Attribute\PostMount;
use Twig\Environment;

#[AsLiveComponent(route: 'live_component_admin')]
class OffersAssign extends AbstractController
{
    use ComponentToolsTrait;
    use DefaultActionTrait;
    use ValidatableComponentTrait;

    #[LiveProp]
    public ?Edition $edition;

    #[LiveProp(onUpdated: 'onOfferUpdated', url: new UrlMapping(as: 'angebot'))]
    public ?OfferInterface $offer = null;

    #[LiveProp]
    public ?ParticipantInterface $participant = null;

    #[LiveProp(writable: true)]
    public string $query = '';

    public function __construct(private readonly EditionRepository $editions, private readonly OfferRepositoryInterface $offers, private readonly ParticipantRepositoryInterface $participants, private readonly UserRepository $users, private readonly HostRepository $hosts, private readonly Environment $twig, private readonly RequestStack $requestStack, private readonly NormalizerInterface $normalizer, private readonly Notifier $notifier, private readonly MailingNotification $mailingNotification)
    {
    }

    #[ExposeInTemplate]
    public function offers(): iterable
    {
        /** @var QueryBuilder $qb */
        $qb = $this->offers->createQueryBuilder('o');
        $qb
            ->addSelect('sum(CASE WHEN a.status = :status_waiting THEN 1 ELSE 0 END) AS HIDDEN countAttendances')
            ->leftJoin('o.hosts', 'h')
            ->leftJoin('o.dates', 'd')
            ->leftJoin('o.attendances', 'a')
            ->andWhere('o.requiresApplication = 1')
            ->andWhere('o.onlineApplication = 1')
            ->addOrderBy('countAttendances', 'DESC')
            ->addOrderBy('CASE WHEN o.state = :state_cancelled THEN 0 ELSE 1 END', 'DESC')
            ->addOrderBy('d.begin')
            ->addGroupBy('o.id')
            ->addGroupBy('d.begin')
            ->setParameter('status_waiting', Attendance::STATUS_WAITING)
            ->setParameter('state_cancelled', OfferInterface::STATE_CANCELLED)
        ;

        $this->addQueryBuilderSearch($qb);

        if (null !== $this->edition) {
            $qb->andWhere('o.edition = :edition')->setParameter('edition', $this->edition);
        }

        if (null !== $this->participant) {
            $qb
                ->innerJoin('a.participant', 'p', Join::WITH, 'p.id = :participant')
                ->setParameter('participant', $this->participant->getId())
            ;
        }

        return $qb->getQuery()->getResult();
    }

    #[ExposeInTemplate]
    public function participants(): iterable
    {
        /** @var QueryBuilder $qb */
        $qb = $this->participants->createQueryBuilder('p');
        $qb
            ->innerJoin('p.attendances', 'a')
            ->addGroupBy('p')
        ;

        if (null !== $this->offer) {
            $qb
                ->innerJoin('a.offer', 'o', Join::WITH, 'o.id = :offer')
                ->addGroupBy('a')
                ->setParameter('offer', $this->offer)
            ;
        } else {
            $qb
                ->innerJoin('a.offer', 'o')
            ;
        }

        if (null !== $this->offer) {
            $qb
                ->addOrderBy('a.userPriority', 'ASC')
                ->addOrderBy('a.status')
                ->addOrderBy('a.createdAt', 'ASC')
            ;
        }

        if (null === $this->offer) {
            $qb
                ->addSelect('sum(CASE WHEN a.status = :status_waiting THEN 1 ELSE 0 END) AS HIDDEN countWaiting')
                ->addSelect('sum(CASE WHEN a.status = :status_confirmed THEN 1 ELSE 0 END) AS HIDDEN countConfirmed')
                ->addOrderBy('countWaiting', 'DESC')
                ->addOrderBy('countConfirmed', 'DESC')
                ->setParameter('status_waiting', Attendance::STATUS_WAITING)
                ->setParameter('status_confirmed', Attendance::STATUS_CONFIRMED)
            ;
        }

        if (null !== $this->edition) {
            $qb->innerJoin('o.edition', 'e', Join::WITH, 'e = :edition')->setParameter('edition', $this->edition);
        }

        //        $qb->addSelect('sum(CASE WHEN a.status = :status_waiting THEN 1 ELSE 0 END) AS HIDDEN countAttendances')
        //            ->leftJoin('o.hosts', 'h')
        //            ->leftJoin('o.dates', 'd')
        //            ->leftJoin('o.attendances', 'a')
        //            ->andWhere('o.requiresApplication = 1')
        //            ->andWhere('o.onlineApplication = 1')
        //            ->addOrderBy('countAttendances', 'DESC')
        //            ->addOrderBy('d.begin')
        //            ->addGroupBy('o.id')
        //            ->addGroupBy('d.begin')
        //            ->setParameter('status_waiting', Attendance::STATUS_WAITING)
        //        ;

        return $qb->getQuery()->getResult();
    }

    #[ExposeInTemplate]
    public function maxApplications(): int
    {
        /** @var QueryBuilder $qb */
        $qb = $this->participants->createQueryBuilder('p');

        $qb
            ->innerJoin('p.attendances', 'a')
            ->innerJoin('a.offer', 'o')
            ->select('sum(CASE WHEN a.status = :status_withdrawn THEN 0 ELSE 1 END) AS countAttendances')
            ->addGroupBy('p')
            ->addOrderBy('countAttendances', 'DESC')
            ->andWhere('a.status <> :status_withdrawn')
            ->setParameter('status_withdrawn', Attendance::STATUS_WITHDRAWN)
        ;

        if (null !== $this->edition) {
            $qb->innerJoin('o.edition', 'e', Join::WITH, 'e.id = :edition')->setParameter('edition', $this->edition->getId());
        }

        $count = $qb->getQuery()->setMaxResults(1)->getSingleScalarResult();

        return max((int) $count, 1);
    }

    #[LiveListener('open')]
    public function openOffer(#[LiveArg('offer')] int $offerId): void
    {
        $this->offer = $this->offers->find($offerId);
    }

    #[LiveListener('selectParticipant')]
    public function selectParticipant(#[LiveArg('participant')] int $participantId): void
    {
        if (null === $this->participant || $participantId !== $this->participant->getId()) {
            $this->participant = $this->participants->find($participantId);
        } else {
            $this->participant = null;
        }
    }

    public function onOfferUpdated($previous): void
    {
    }

    #[PostMount]
    public function postMount(): void
    {
        // Fix invalid entity when ?angebot=
        if (null === $this->offer?->getId()) {
            $this->offer = null;
        }
    }

    private function addQueryBuilderSearch(QueryBuilder $qb): void
    {
        $where = $qb->expr()->andX();
        foreach (array_filter(StringUtil::trimsplit(' ', $this->query)) as $j => $token) {
            foreach (['o.name', 'h.name'] as $field) {
                $where->add("$field LIKE :query_$j");
            }

            $qb->setParameter("query_$j", "%{$token}%");
        }

        if ($where->count()) {
            $qb->andWhere($where);
        }
    }
}
