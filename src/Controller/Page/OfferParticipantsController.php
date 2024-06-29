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
use Ferienpass\AdminBundle\ApplicationSystem\ParticipantList;
use Ferienpass\AdminBundle\Breadcrumb\Breadcrumb;
use Ferienpass\AdminBundle\LiveComponent\MultiSelect;
use Ferienpass\AdminBundle\LiveComponent\MultiSelectHandlerInterface;
use Ferienpass\CoreBundle\Entity\Attendance;
use Ferienpass\CoreBundle\Entity\Offer\OfferInterface;
use Ferienpass\CoreBundle\Entity\User;
use Ferienpass\CoreBundle\Export\ParticipantList\PdfExport;
use Ferienpass\CoreBundle\Facade\AttendanceFacade;
use Ferienpass\CoreBundle\Repository\AttendanceRepository;
use Ferienpass\CoreBundle\Repository\HostConsentRepository;
use Ferienpass\CoreBundle\Repository\OfferRepositoryInterface;
use Ferienpass\CoreBundle\Session\Flash;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/angebote/{edition?null}/{id}/teilnahmeliste{_suffix}', name: 'admin_offer_participants', requirements: ['id' => '\d+'], defaults: ['_suffix' => ''])]
final class OfferParticipantsController extends AbstractController implements MultiSelectHandlerInterface
{
    public function __construct(private readonly ParticipantList $participantList, private readonly HostConsentRepository $consents, private readonly AttendanceRepository $attendances, private readonly Flash $flash)
    {
    }

    public function __invoke(string $_suffix, int $id, OfferRepositoryInterface $offers, AttendanceRepository $attendanceRepository, Request $request, PdfExport $pdfExport, EntityManagerInterface $em, AttendanceFacade $attendanceFacade, Breadcrumb $breadcrumb, Flash $flash): Response
    {
        /** @var OfferInterface $offer */
        if (null === $offer = $offers->find($id)) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted('participants.view', $offer);

        $qb = $attendanceRepository->createQueryBuilder('i');
        $qb
            ->innerJoin('i.participant', 'p')
            ->innerJoin('i.offer', 'o')
            ->where('o.id = :offer')
            ->setParameter('offer', $offer->getId())
        ;

        $_suffix = ltrim($_suffix, '.');
        if ('pdf' === $_suffix) {
            return $this->file($pdfExport->generate($offer), 'teilnahmeliste.pdf');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        if (null === $this->consents->findValid($user)) {
            return $this->render('@FerienpassAdmin/page/missing_privacy_statement.html.twig', [
                'breadcrumb' => $breadcrumb->generate(['offers.title', ['route' => 'admin_offers_index', 'routeParameters' => ['edition' => $offer->getEdition()->getAlias()]]], [$offer->getEdition()->getName(), ['route' => 'admin_offers_index', 'routeParameters' => ['edition' => $offer->getEdition()->getAlias()]]], $offer->getName(), 'Anmeldungen'),
            ]);
        }

        $ms = new MultiSelect(['confirm', 'confirmAndInform', 'reject', 'rejectAndInform'], $this::class);

        return $this->render('@FerienpassAdmin/page/offers/participant_list.html.twig', [
            'qb' => $qb,
            'searchable' => ['p.firstname', 'p.lastname'],
            'ms' => $ms,
            'offer' => $offer,
            'breadcrumb' => $breadcrumb->generate(['offers.title', ['route' => 'admin_offers_index', 'routeParameters' => ['edition' => $offer->getEdition()->getAlias()]]], [$offer->getEdition()->getName(), ['route' => 'admin_offers_index', 'routeParameters' => ['edition' => $offer->getEdition()->getAlias()]]], $offer->getName(), 'Anmeldungen'),
        ]);

        // $this->denyAccessUnlessGranted('participants.add', $offer);
    }

    public function handleMultiSelect(string $action, array $selected, Request $request): Response
    {
        $action = match ($action) {
            'confirm', 'confirmAndInform' => 'confirm',
            'reject', 'rejectAndInform' => 'reject',
            default => throw new \InvalidArgumentException('Button not found'),
        };

        $notify = \in_array($action, ['confirmAndInform', 'rejectAndInform'], true);
        /** @var Attendance[] $selectedParticipants */
        $selectedParticipants = $this->attendances->findBy(['id' => $selected]);
        $offer = $selectedParticipants[0]->getOffer();

        $this->denyAccessUnlessGranted("participants.$action", $offer);

        if ('confirm' === $action) {
            $this->participantList->confirm($selectedParticipants, reorder: false, notify: $notify);

            $this->flash->addConfirmation(text: 'Den Teilnehmer:innen wurde zugesagt.');
        }

        if ('reject' === $action) {
            $this->participantList->reject($selectedParticipants, reorder: false, notify: $notify);

            $this->flash->addConfirmation(text: 'Den Teilnehmer:innen wurde abgesagt.');
        }

        return $this->redirectToRoute('admin_offer_participants', ['id' => $offer->getId(), 'edition' => $offer->getEdition()?->getAlias()]);
    }
}
