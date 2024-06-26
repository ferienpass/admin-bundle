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

use Doctrine\DBAL\Types\Types;
use Ferienpass\CoreBundle\Entity\Offer\OfferInterface;
use Ferienpass\CoreBundle\Entity\User;
use Ferienpass\CoreBundle\Export\Offer\OfferExporter;
use Ferienpass\CoreBundle\Message\ExportOffers;
use Ferienpass\CoreBundle\Repository\EditionRepository;
use Ferienpass\CoreBundle\Repository\OfferRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ValidatableComponentTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsLiveComponent(route: 'live_component_admin')]
final class Export extends AbstractController
{
    use ComponentToolsTrait;
    use DefaultActionTrait;
    use ValidatableComponentTrait;

    #[LiveProp(writable: true)]
    #[Assert\NotBlank()]
    public ?string $export = null;

    #[LiveProp(writable: true)]
    public array $selectedEditions = [];

    #[LiveProp(writable: true)]
    public array $selectedHosts = [];

    #[LiveProp(writable: true)]
    public bool $onlyPublished = true;

    public function __construct(private readonly OfferRepositoryInterface $offers, private readonly EditionRepository $editions, private readonly OfferExporter $exporter)
    {
    }

    #[ExposeInTemplate]
    public function exporterOptions(): array
    {
        return $this->exporter->getAllNames();
    }

    #[ExposeInTemplate]
    public function editionOptions(): array
    {
        return $this->editions->findBy(['archived' => 0]);
    }

    #[LiveListener('selectExport')]
    public function selectExport(#[LiveArg] string $name): void
    {
        $this->export = $name;
    }

    #[LiveAction]
    public function submit(#[CurrentUser] User $user, MessageBusInterface $messageBus): void
    {
        $this->validate();

        $offers = $this->queryOffers();
        if (empty($offers)) {
            // TODO warning
        }

        $messageBus->dispatch(new ExportOffers($this->export, $offers, $user->getEmail()));

        // TODO send flash
        $this->export = null;
    }

    private function queryOffers(): array
    {
        $qb = $this->offers
            ->createQueryBuilder('offer')
            ->select('offer.id')
            ->leftJoin('offer.dates', 'dates')
            ->orderBy('dates.begin', 'ASC')
        ;

        if ($this->onlyPublished) {
            $qb->andWhere('offer.state = :state_published');
            $qb->setParameter('state_published', OfferInterface::STATE_PUBLISHED);
        }

        if ($this->selectedEditions) {
            $qb->andWhere('offer.edition IN (:editions)')->setParameter('editions', $this->selectedEditions, Types::SIMPLE_ARRAY);
        }

        if ($this->selectedHosts) {
            $qb->innerJoin('offer.hosts', 'hosts')->andWhere('hosts.id IN (:hosts)')->setParameter('hosts', $this->selectedHosts, Types::SIMPLE_ARRAY);
        }

        return $qb->getQuery()->getSingleColumnResult();
    }
}
