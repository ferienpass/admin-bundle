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
use Ferienpass\AdminBundle\Form\Filter\PaymentsFilter;
use Ferienpass\AdminBundle\LiveComponent\MultiSelect;
use Ferienpass\AdminBundle\LiveComponent\MultiSelectHandlerInterface;
use Ferienpass\CoreBundle\Entity\Attendance;
use Ferienpass\CoreBundle\Entity\Payment;
use Ferienpass\CoreBundle\Entity\PaymentItem;
use Ferienpass\CoreBundle\Export\Payments\ReceiptPdfExport;
use Ferienpass\CoreBundle\Message\ParticipantListChanged;
use Ferienpass\CoreBundle\Message\PaymentReceiptCreated;
use Ferienpass\CoreBundle\Payments\ReceiptNumberGenerator;
use Ferienpass\CoreBundle\Repository\PaymentItemRepository;
use Ferienpass\CoreBundle\Repository\PaymentRepository;
use Ferienpass\CoreBundle\Session\Flash;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/zahlungen')]
final class PaymentsController extends AbstractController implements MultiSelectHandlerInterface
{
    public function __construct(private readonly ReceiptPdfExport $receiptExport, private readonly PaymentItemRepository $paymentItems, private readonly Flash $flash, private readonly ReceiptNumberGenerator $numberGenerator, private readonly EntityManagerInterface $entityManager, private readonly MessageBusInterface $messageBus, #[TaggedLocator(ExportQueryBuilderInterface::class, defaultIndexMethod: 'getFormat')] private readonly ServiceLocator $exporters)
    {
    }

    #[Route('', name: 'admin_payments_index')]
    public function index(PaymentRepository $repository, Breadcrumb $breadcrumb): Response
    {
        $qb = $repository->createQueryBuilder('i');
        $qb->orderBy('i.createdAt', 'DESC');

        return $this->render('@FerienpassAdmin/page/payments/index.html.twig', [
            'qb' => $qb,
            'filterType' => PaymentsFilter::class,
            'exports' => array_keys($this->exporters->getProvidedServices()),
            'searchable' => ['billingAddress', 'billingEmail', 'receiptNumber'],
            'breadcrumb' => $breadcrumb->generate('payments.title'),
        ]);
    }

    #[Route('/export.{format}', name: 'admin_payments_export', requirements: ['format' => '\w+'])]
    public function export(PaymentRepository $repository, string $format)
    {
        $qb = $repository->createQueryBuilder('i');
        $qb->orderBy('i.createdAt', 'DESC');

        if (!$this->exporters->has($format)) {
            throw $this->createNotFoundException();
        }

        $exporter = $this->exporters->get($format);

        return $this->file($exporter->generate($qb), "zahlungen.$format");
    }

    #[Route('/{id}', name: 'admin_payments_receipt', requirements: ['id' => '\d+'])]
    public function show(Payment $payment, Breadcrumb $breadcrumb): Response
    {
        return $this->render('@FerienpassAdmin/page/payments/receipt.html.twig', [
            'payment' => $payment,
            'breadcrumb' => $breadcrumb->generate(['payments.title', ['route' => 'admin_payments_index']], 'Beleg #'.$payment->getReceiptNumber()),
        ]);
    }

    #[Route('/{id}/storno', name: 'admin_payments_reverse')]
    public function reverse(Payment $payment, PaymentItemRepository $paymentItemsRepository, Breadcrumb $breadcrumb): Response
    {
        $qb = $paymentItemsRepository->createQueryBuilder('i');
        $qb
            ->innerJoin('i.payment', 'p')
            ->leftJoin('i.attendance', 'a')
            ->leftJoin('a.offer', 'o')
            ->where('p = :payment')
            ->setParameter('payment', $payment)
        ;

        $ms = new MultiSelect(['reverse', 'reverseAndWithdraw'], $this::class);

        return $this->render('@FerienpassAdmin/page/payments/reverse.html.twig', [
            'qb' => $qb,
            'ms' => $ms,
            'searchable' => ['o.name'],
            'breadcrumb' => $breadcrumb->generate(['payments.title', ['route' => 'admin_payments_index']], 'Beleg #'.$payment->getReceiptNumber(), 'Storno'),
        ]);
    }

    public function handleMultiSelect(string $action, array $selected, Request $request): Response
    {
        if (!\in_array($action, ['reverse', 'reverseAndWithdraw'], true)) {
            throw new \RuntimeException('Code should not be reached');
        }

        $user = $this->getUser();

        /** @var PaymentItem[] $items */
        $items = $this->paymentItems->createQueryBuilder('i')
            ->leftJoin('i.attendance', 'a')
            ->where('i.id IN (:ids)')
            ->andWhere('a.paid = 1')
            ->setParameter('ids', $selected)
            ->getQuery()
            ->getResult()
        ;

        if (empty($items)) {
            $this->flash->addError(text: 'Es wurde nichts storniert. Entweder wurde keine Auswahl getroffen, oder die Buchungen waren schon storniert.');

            return $this->redirectToRoute('admin_payments_index');
        }

        $payment = $items[0]->getPayment();

        $reversalPayment = new Payment($this->numberGenerator->generate(), $user);
        $reversalPayment->setBillingAddress($payment->getBillingAddress());
        $reversalPayment->setBillingEmail($payment->getBillingEmail());
        foreach ($items as $item) {
            $reversalPayment->addItem(new PaymentItem($reversalPayment, $item->getAttendance(), (-1) * $item->getAmount()));
        }

        $reversalPayment->getItems()->map(fn (PaymentItem $item) => $item->getAttendance()->setPaid(false));

        if ('reverseAndWithdraw' === $action) {
            foreach ($reversalPayment->getItems() as $item) {
                if ($item->getAttendance()->isWithdrawn()) {
                    continue;
                }

                $item->getAttendance()->setStatus(Attendance::STATUS_WITHDRAWN);

                $this->messageBus->dispatch(new ParticipantListChanged($item->getAttendance()->getOffer()->getId()));
            }
        }

        $this->entityManager->persist($reversalPayment);
        $this->entityManager->flush();

        $this->messageBus->dispatch(new PaymentReceiptCreated($reversalPayment->getId()));

        $this->flash->addConfirmation(text: 'Der Stornobeleg wurde erstellt.');

        return $this->redirectToRoute('admin_payments_receipt', ['id' => $reversalPayment->getId()]);
    }

    #[Route('/{id}.pdf', name: 'admin_payments_receipt_pdf', requirements: ['id' => '\d+'])]
    public function pdf(Payment $payment): Response
    {
        $path = $this->receiptExport->generate($payment);

        return $this->file($path, sprintf('beleg-%s.pdf', $payment->getId()));
    }
}
