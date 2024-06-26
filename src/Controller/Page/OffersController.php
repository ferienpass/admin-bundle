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
use Ferienpass\AdminBundle\Export\ExportQueryBuilderInterface;
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
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\WorkflowInterface;

#[Route('/angebote/{edition?null}')]
final class OffersController extends AbstractController
{
    public function __construct(#[TaggedLocator(ExportQueryBuilderInterface::class, defaultIndexMethod: 'getFormat')] private readonly ServiceLocator $exporters)
    {
    }

    #[Route('', name: 'admin_offers_index')]
    public function index(#[MapEntity(mapping: ['edition' => 'alias'])] ?Edition $edition, OfferRepositoryInterface $repository, Breadcrumb $breadcrumb, FactoryInterface $factory, EditionRepository $editions): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \RuntimeException('No user');
        }

        $qb = $repository->createQueryBuilder('i')
            ->leftJoin('i.dates', 'd')
        ;

        $menu = $factory->createItem('offers.editions');

        foreach ($editions->findBy(['archived' => false], ['createdAt' => 'DESC']) as $e) {
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
            'exports' => $this->isGranted('ROLE_ADMIN') ? array_keys($this->exporters->getProvidedServices()) : [],
            'searchable' => ['name'],
            'items' => $qb->getQuery()->getResult(),
            'edition' => $edition,
            'uncompletedOffers' => (clone $qb)->select('COUNT(i)')->andWhere('i.state = :status')->setParameter('status', OfferInterface::STATE_DRAFT)->getQuery()->getSingleResult() > 0,
            'aside_nav' => $menu,
            'breadcrumb' => $breadcrumb->generate('offers.title', $edition?->getName()),
        ]);
    }

    #[Route('/export.{format}', name: 'admin_offers_export', requirements: ['format' => '\w+'])]
    public function export(OfferRepositoryInterface $repository, string $format)
    {
        $qb = $repository->createQueryBuilder('i');
        $qb->orderBy('i.createdAt', 'DESC');

        if (!$this->exporters->has($format)) {
            throw $this->createNotFoundException();
        }

        $exporter = $this->exporters->get($format);

        return $this->file($exporter->generate($qb), "angebote.$format");
    }

    #[Route('/{id}', name: 'admin_offers_show', requirements: ['id' => '\d+'])]
    public function show(int $id, OfferRepositoryInterface $repository, Breadcrumb $breadcrumb): Response
    {
        if (null === $offer = $repository->find($id)) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted('view', $offer);

        return $this->render('@FerienpassAdmin/page/offers/show.html.twig', [
            'item' => $offer,
            'breadcrumb' => $breadcrumb->generate(['offers.title', ['route' => 'admin_offers_index', 'routeParameters' => ['edition' => $offer->getEdition()->getAlias()]]], [$offer->getEdition()->getName(), ['route' => 'admin_offers_index', 'routeParameters' => ['edition' => $offer->getEdition()->getAlias()]]], $offer->getName()),
        ]);
    }

    #[Route('/neu', name: 'admin_offers_new')]
    public function newWizard(#[MapEntity(mapping: ['edition' => 'alias'])] ?Edition $edition, Breadcrumb $breadcrumb): Response
    {
        $this->denyAccessUnlessGranted('offer.create', $edition);

        return $this->render('@FerienpassAdmin/page/offers/new.html.twig', [
            'edition' => $edition,
            'breadcrumb' => $breadcrumb->generate(['offers.title', ['route' => 'admin_offers_index', 'routeParameters' => ['edition' => $edition->getAlias()]]], [$edition->getName(), ['route' => 'admin_offers_index', 'routeParameters' => ['edition' => $edition->getAlias()]]], 'Neue Veranstaltung'),
        ]);
    }

    #[Route('/{id}/korrekturabzug', name: 'admin_offers_proof', requirements: ['id' => '\d+'])]
    public function proof(int $id, OfferRepositoryInterface $repository, PdfExports $pdfExports, Breadcrumb $breadcrumb): Response
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

    #[Route('/{id}/löschen', name: 'admin_offers_delete', requirements: ['id' => '\d+'])]
    public function delete(int $id, OfferRepositoryInterface $repository, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (null === $item = $repository->find($id)) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted('delete', $item);

        $form = $this->createForm(FormType::class, options: [
            'action' => $this->generateUrl('admin_offers_delete', ['id' => $id]),
        ]);
        $form->handleRequest($request);

        // TODO Can use a facade
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var $attendance */
            foreach ($item->getAttendances() as $attendance) {
                foreach ($attendance->getPaymentItems() as $paymentItem) {
                    $paymentItem->removeAttendanceAssociation();
                }
            }

            if ($item->hasVariants()) {
                $variants = $item->getVariants()->toArray();
                $variants[0]->setVariantBase(null);

                for ($i = 1; $i < \count($variants); ++$i) {
                    $variants[$i]->setVariantBase($variants[0]);
                }
            } elseif (!$item->isVariantBase()) {
                $item->setVariantBase(null);
            }

            $entityManager->remove($item);
            $entityManager->flush();
        }

        return $this->render('@FerienpassAdmin/page/offers/delete.html.twig', [
            'item' => $item,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/absagen', name: 'admin_offers_cancel', requirements: ['id' => '\d+'])]
    public function cancel(int $id, OfferRepositoryInterface $repository, Request $request, EntityManagerInterface $entityManager, WorkflowInterface $offerStateMachine, Breadcrumb $breadcrumb): Response
    {
        if (null === $offer = $repository->find($id)) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(OfferInterface::TRANSITION_CANCEL, $offer);

        $form = $this->createForm(FormType::class, options: [
            'action' => $this->generateUrl('admin_offers_cancel', ['id' => $id]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $offerStateMachine->apply($offer, OfferInterface::TRANSITION_CANCEL);
            $entityManager->flush();
        }

        return $this->render('@FerienpassAdmin/page/offers/cancel.html.twig', [
            'offer' => $offer,
            'cancel' => $form,
            'breadcrumb' => $breadcrumb->generate(['offers.title', ['route' => 'admin_offers_index', 'routeParameters' => ['edition' => $offer->getEdition()->getAlias()]]], [$offer->getEdition()->getName(), ['route' => 'admin_offers_index', 'routeParameters' => ['edition' => $offer->getEdition()->getAlias()]]], $offer->getName()),
        ]);
    }

    #[Route('/{id}/wiederherstellen', name: 'admin_offers_relaunch', requirements: ['id' => '\d+'])]
    public function relaunch(int $id, OfferRepositoryInterface $repository, Request $request, EntityManagerInterface $entityManager, WorkflowInterface $offerStateMachine, Breadcrumb $breadcrumb): Response
    {
        if (null === $offer = $repository->find($id)) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(OfferInterface::TRANSITION_RELAUNCH, $offer);

        $form = $this->createForm(FormType::class, options: [
            'action' => $this->generateUrl('admin_offers_relaunch', ['id' => $id]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $offerStateMachine->apply($offer, OfferInterface::TRANSITION_RELAUNCH);
            $entityManager->flush();
        }

        return $this->render('@FerienpassAdmin/page/offers/relaunch.html.twig', [
            'offer' => $offer,
            'relaunch' => $form,
            'breadcrumb' => $breadcrumb->generate(['offers.title', ['route' => 'admin_offers_index', 'routeParameters' => ['edition' => $offer->getEdition()->getAlias()]]], [$offer->getEdition()->getName(), ['route' => 'admin_offers_index', 'routeParameters' => ['edition' => $offer->getEdition()->getAlias()]]], $offer->getName()),
        ]);
    }
}
