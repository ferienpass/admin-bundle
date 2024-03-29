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

namespace Ferienpass\AdminBundle\Controller\Page;

use Doctrine\ORM\EntityManagerInterface;
use Ferienpass\AdminBundle\Breadcrumb\Breadcrumb;
use Ferienpass\AdminBundle\Export\XlsxExport;
use Ferienpass\AdminBundle\Form\Filter\OffersFilter;
use Ferienpass\CoreBundle\Entity\Edition;
use Ferienpass\CoreBundle\Entity\Offer\OfferInterface;
use Ferienpass\CoreBundle\Entity\User;
use Ferienpass\CoreBundle\Export\Offer\PrintSheet\PdfExports;
use Ferienpass\CoreBundle\Repository\EditionRepository;
use Ferienpass\CoreBundle\Repository\OfferRepositoryInterface;
use Knp\Menu\FactoryInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/angebote/{edition?null}')]
final class OffersController extends AbstractController
{
    #[Route('{_suffix?}', name: 'admin_offers_index')]
    public function index(#[MapEntity(mapping: ['edition' => 'alias'])] ?Edition $edition, ?string $_suffix, OfferRepositoryInterface $repository, Breadcrumb $breadcrumb, FactoryInterface $factory, EditionRepository $editionRepository, XlsxExport $xlsxExport, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \RuntimeException('No user');
        }

        $qb = $repository->createQueryBuilder('i');

        $_suffix = ltrim((string) $_suffix, '.');
        if ('' !== $_suffix) {
            // TODO service-tagged exporter
            if ('xlsx' === $_suffix) {
                return $this->file($xlsxExport->generate($qb), 'angebote.xlsx');
            }
        }

        $menu = $factory->createItem('offers.editions');

        foreach ($editionRepository->findBy(['archived' => false], ['createdAt' => 'DESC']) as $e) {
            if (!$this->isGranted('ROLE_ADMIN') && !$e->getHosts()->isEmpty() && !$e->getHosts()->contains($user->getHosts()->first())) {
                continue;
            }

            $menu->addChild($e->getName(), [
                'route' => 'admin_offers_index',
                'routeParameters' => ['edition' => $e->getAlias()],
                'current' => null !== $edition && $e->getAlias() === $edition->getAlias(),
            ]);
        }

        return $this->render('@FerienpassAdmin/page/offers/index.html.twig', [
            'qb' => $qb,
            'filterType' => OffersFilter::class,
            'createUrl' => null === $edition || $this->isGranted('offer.create', $edition) ? $this->generateUrl('admin_offers_new', array_filter(['edition' => $edition?->getAlias()])) : null,
            'exports' => $this->isGranted('ROLE_ADMIN') ? ['xlsx'] : [],
            'searchable' => ['name'],
            'items' => $qb->getQuery()->getResult(),
            'edition' => $edition,
            'uncompletedOffers' => (clone $qb)->select('COUNT(i)')->andWhere('i.state = :status')->setParameter('status', OfferInterface::STATE_DRAFT)->getQuery()->getSingleResult() > 0,
            'aside_nav' => $menu,
            'breadcrumb' => $breadcrumb->generate('offers.title', $edition?->getName()),
        ]);
    }

    #[Route('/{id}', name: 'admin_offer_proof', requirements: ['id' => '\d+'])]
    public function show(int $id, OfferRepositoryInterface $repository, PdfExports $pdfExports, Breadcrumb $breadcrumb): Response
    {
        if (null === $offer = $repository->find($id)) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted('view', $offer);

        return $this->render('@FerienpassAdmin/page/offers/proof.html.twig', [
            'offer' => $offer,
            'hasPdf' => $pdfExports->has(),
            'breadcrumb' => $breadcrumb->generate(['offers.title', ['route' => 'admin_offers_index', 'routeParameters' => ['edition' => $offer->getEdition()->getAlias()]]], [$offer->getEdition()->getName(), ['route' => 'admin_offers_index', 'routeParameters' => ['edition' => $offer->getEdition()->getAlias()]]], $offer->getName()),
        ]);
    }
}
