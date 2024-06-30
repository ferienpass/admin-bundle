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
use Doctrine\Persistence\ManagerRegistry;
use Ferienpass\AdminBundle\Breadcrumb\Breadcrumb;
use Ferienpass\AdminBundle\Dto\BillingAddressDto;
use Ferienpass\AdminBundle\Export\XlsxExport;
use Ferienpass\AdminBundle\Form\EditParticipantType;
use Ferienpass\AdminBundle\Form\Filter\AttendancesFilter;
use Ferienpass\AdminBundle\Form\Filter\ParticipantFilter;
use Ferienpass\AdminBundle\Form\SettleAttendancesType;
use Ferienpass\AdminBundle\LiveComponent\MultiSelect;
use Ferienpass\AdminBundle\LiveComponent\MultiSelectHandlerInterface;
use Ferienpass\CoreBundle\Entity\Attendance;
use Ferienpass\CoreBundle\Entity\Participant\ParticipantInterface;
use Ferienpass\CoreBundle\Entity\Payment;
use Ferienpass\CoreBundle\Entity\PaymentItem;
use Ferienpass\CoreBundle\Message\PaymentReceiptCreated;
use Ferienpass\CoreBundle\Payments\ReceiptNumberGenerator;
use Ferienpass\CoreBundle\Repository\AttendanceRepository;
use Ferienpass\CoreBundle\Repository\ParticipantRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[IsGranted('ROLE_PARTICIPANTS_ADMIN')]
#[Route('/teilnehmende')]
final class ParticipantsController extends AbstractController implements MultiSelectHandlerInterface
{
    public function __construct(private readonly ManagerRegistry $doctrine, private readonly ReceiptNumberGenerator $numberGenerator)
    {
    }

    #[Route('{_suffix?}', name: 'admin_participants_index', requirements: ['_suffix' => '\.\w+'])]
    public function index(ParticipantRepositoryInterface $repository, Breadcrumb $breadcrumb, ?string $_suffix, XlsxExport $xlsxExport): Response
    {
        $qb = $repository->createQueryBuilder('i');

        $qb
            ->leftJoin('i.attendances', 'a')
            ->addSelect('a')
            ->leftJoin('i.activity', 'l')
            ->addSelect('l')
        ;

        // $filter = $this->filterFactory->create($qb)->applyFilter($request->query->all());

        $_suffix = ltrim((string) $_suffix, '.');
        if ('' !== $_suffix) {
            // TODO service-tagged exporter
            if ('xlsx' === $_suffix) {
                return $this->file($xlsxExport->generate($qb), 'teilnehmende.xlsx');
            }
        }

        return $this->render('@FerienpassAdmin/page/participants/index.html.twig', [
            'qb' => $qb,
            'filterType' => ParticipantFilter::class,
            'exports' => ['xlsx'],
            'searchable' => ['firstname', 'lastname', 'email', 'mobile', 'phone'],
            'createUrl' => $this->generateUrl('admin_participants_create'),
            'breadcrumb' => $breadcrumb->generate('participants.title'),
        ]);
    }

    #[Route('/neu', name: 'admin_participants_create')]
    #[Route('/{id}/bearbeiten', name: 'admin_participants_edit', requirements: ['id' => '\d+'])]
    public function edit(?int $id, Request $request, Breadcrumb $breadcrumb, ParticipantRepositoryInterface $repository): Response
    {
        if (null !== $id && null === ($participant = $repository->find($id))) {
            throw $this->createNotFoundException();
        }

        $participant ??= $repository->createNew();

        $em = $this->doctrine->getManager();
        $form = $this->createForm(EditParticipantType::class, $participant);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$em->contains($participant = $form->getData())) {
                $em->persist($participant);
            }

            $em->flush();

            return $this->redirectToRoute('admin_participants_edit', ['id' => $participant->getId()]);
        }

        $breadcrumbTitle = $participant ? $participant->getName().' (bearbeiten)' : 'participants.new';

        /** @noinspection FormViewTemplate `createView()` messes ups error handling/redirect */
        return $this->render('@FerienpassAdmin/page/participants/edit.html.twig', [
            'item' => $participant,
            'form' => $form,
            'breadcrumb' => $breadcrumb->generate(['participants.title', ['route' => 'admin_participants_index']], $breadcrumbTitle),
        ]);
    }

    #[Route('/{id}', name: 'admin_participants_show', requirements: ['id' => '\d+'])]
    public function show(int $id, ParticipantRepositoryInterface $repository, Breadcrumb $breadcrumb): Response
    {
        if (null === $participant = $repository->find($id)) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted('view', $participant);

        return $this->render('@FerienpassAdmin/page/participants/show.html.twig', [
            'item' => $participant,
            'breadcrumb' => $breadcrumb->generate(['participants.title', ['route' => 'admin_participants_index']], $participant->getName()),
        ]);
    }

    #[Route('/{id}/anmeldungen', name: 'admin_participants_attendances', requirements: ['id' => '\d+'])]
    public function attendances(int $id, ParticipantRepositoryInterface $participantRepository, AttendanceRepository $attendanceRepository, Request $request, Breadcrumb $breadcrumb): Response
    {
        /** @var ParticipantInterface $participant */
        $participant = $participantRepository->find($id);
        if (null === $participant) {
            throw $this->createNotFoundException();
        }

        $qb = $attendanceRepository->createQueryBuilder('i');
        $qb
            ->leftJoin('i.offer', 'o')
            ->leftJoin('o.dates', 'd')
            ->innerJoin('i.participant', 'p')
            ->where('p.id = :participant')
            ->setParameter('participant', $participant->getId())
        ;

        /** @var Attendance[] $items */
        $items = $qb->getQuery()->getResult();

        $ms = new MultiSelect(['settle'], $this::class, array_values(array_map(fn (Attendance $a) => $a->getId(), array_filter($items, fn (Attendance $a) => $a->isConfirmed() && !$a->isPaid() && !$a->getOffer()->getEdition()?->isCompleted()))));

        return $this->render('@FerienpassAdmin/page/participants/attendances.html.twig', [
            'qb' => $qb,
            'filterType' => AttendancesFilter::class,
            'ms' => $ms,
            'searchable' => ['o.name'],
            'participant' => $participant,
            'breadcrumb' => $breadcrumb->generate(['participants.title', ['route' => 'admin_participants_index']], $participant->getName().' (Anmeldungen)'),
        ]);
    }

    public function handleMultiSelect(string $action, array $selected, Request $request): Response
    {
        if ('settle' === $action) {
            $request->getSession()->set('ms.settle', array_values($selected));

            return $this->redirectToRoute('admin_attendances_settle');
        }

        throw new \RuntimeException('Code should not be reached');
    }

    #[Route('/{id}/löschen', name: 'admin_participants_delete', requirements: ['id' => '\d+'])]
    public function delete(int $id, ParticipantRepositoryInterface $repository, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (null === $item = $repository->find($id)) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted('delete', $item);

        $form = $this->createForm(FormType::class, options: [
            'action' => $this->generateUrl('admin_participants_delete', ['id' => $id]),
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

            $entityManager->remove($item);
            $entityManager->flush();
        }

        return $this->render('@FerienpassAdmin/page/participants/delete.html.twig', [
            'item' => $item,
            'form' => $form,
        ]);
    }

    #[Route('/abrechnen', name: 'admin_attendances_settle')]
    public function settle(Request $request, Breadcrumb $breadcrumb, AttendanceRepository $attendanceRepository, MessageBusInterface $messageBus, EventDispatcherInterface $dispatcher): Response
    {
        $user = $this->getUser();
        $attendances = $this->getAttendancesFromRequest($attendanceRepository, $request);
        $attendances = array_filter($attendances, fn (Attendance $a) => !$a->isPaid());

        $draftPayment = Payment::fromAttendances($attendances, $dispatcher);

        $form = $this->createForm(SettleAttendancesType::class, $dto = BillingAddressDto::fromPayment($draftPayment), ['attendances' => $attendances]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $payment = new Payment($this->numberGenerator->generate(), $user);
            $dto->toPayment($payment);

            $payment->getItems()->map(fn (PaymentItem $item) => $item->getAttendance()->setPaid());

            $em = $this->doctrine->getManager();
            $em->persist($payment);
            $em->flush();

            $messageBus->dispatch(new PaymentReceiptCreated($payment->getId()));

            return $this->redirectToRoute('admin_payments_receipt', ['id' => $payment->getId()]);
        }

        /** @noinspection FormViewTemplate `createView()` messes ups error handling/redirect */
        return $this->render('@FerienpassAdmin/page/participants/settle.html.twig', [
            'form' => $form,
            'payment' => $draftPayment,
            'breadcrumb' => $breadcrumb->generate(['participants.title', ['route' => 'admin_participants_index']], 'Anmeldungen abrechnen'),
        ]);
    }

    /**
     * @return array<Attendance>
     */
    private function getAttendancesFromRequest(AttendanceRepository $attendances, Request $request): array
    {
        $session = $request->getSession();
        if ($session->has('ms.settle')) {
            $ids = $session->get('ms.settle');
        }

        if ($request->request->has(SettleAttendancesType::FORM_NAME)) {
            $ids = $request->get(SettleAttendancesType::FORM_NAME)['ms'] ?? [];
        }

        if (null === ($ids ?? null)) {
            return [];
        }

        return $attendances->findBy(['id' => $ids]);
    }
}
