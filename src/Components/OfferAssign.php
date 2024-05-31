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

use Doctrine\ORM\EntityManagerInterface;
use Ferienpass\CoreBundle\Entity\Attendance;
use Ferienpass\CoreBundle\Entity\Offer\OfferInterface;
use Ferienpass\CoreBundle\Message\AttendanceStatusChanged;
use Ferienpass\CoreBundle\Message\ParticipantListChanged;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(route: 'live_component_admin')]
class OfferAssign extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp]
    public OfferInterface $offer;

    #[LiveProp(writable: true)]
    public bool $autoAssign;

    public function __construct()
    {
        $this->autoAssign = false;
    }

    #[LiveAction]
    public function confirmAllWaiting(EntityManagerInterface $em, MessageBusInterface $messageBus): void
    {
        $attendances = $this->offer->getAttendancesWaiting();

        $lastAttendance = $this->offer->getAttendancesConfirmed()->last();
        $sorting = $lastAttendance ? $lastAttendance->getSorting() : 0;

        foreach ($attendances as $attendance) {
            $attendance->setStatus(Attendance::STATUS_CONFIRMED);
            $attendance->setSorting($sorting += 128);

            $messageBus->dispatch(new AttendanceStatusChanged($attendance->getId(), Attendance::STATUS_WAITING, $attendance->getStatus(), notify: $this->autoAssign));
        }

        $em->flush();
    }

    #[LiveListener('statusChanged')]
    public function changeStatus(#[LiveArg] Attendance $attendance, #[LiveArg] string $newStatus, #[LiveArg] int $newIndex, MessageBusInterface $messageBus, EntityManagerInterface $em): void
    {
        $this->denyAccessUnlessGranted('participants.view', $attendance->getOffer());

        if (null === $attendance->getParticipant()) {
            return;
        }

        $offer = $attendance->getOffer();
        $oldStatus = $attendance->getStatus();

        $attendance->setStatus($newStatus);
        $this->reorderList($attendance, $newIndex);

        $em->flush();

        // Refresh because we changed the order of the attendances off-reference and that messes re-render
        $em->refresh($this->offer);

        $messageBus->dispatch(new AttendanceStatusChanged($attendance->getId(), $oldStatus, $attendance->getStatus(), notify: $this->autoAssign));

        // Update participant list (move-up participants)
        // WHEN the current participant was not added to the wait-list explicitly,
        // otherwise, it might become confirmed immediately.
        if ($this->autoAssign && !$attendance->isWaitlisted()) {
            $messageBus->dispatch(new ParticipantListChanged($offer->getId()));
        }
    }

    #[LiveListener('indexUpdated')]
    public function changeIndex(#[LiveArg] Attendance $attendance, #[LiveArg] int $newIndex, EntityManagerInterface $em): void
    {
        $this->denyAccessUnlessGranted('participants.view', $attendance->getOffer());

        $this->reorderList($attendance, $newIndex);

        $em->flush();

        // Refresh because we changed the order of the attendances off-reference and that messes re-render
        $em->refresh($this->offer);
    }

    private function reorderList(Attendance $attendance, int $newIndex): void
    {
        /** @var Attendance[] $attendances */
        $attendances = array_values($attendance->getOffer()->getAttendancesWithStatus($attendance->getStatus())->toArray());
        $fromIndex = array_search($attendance, $attendances, true);

        array_splice($attendances, $newIndex, 0, array_splice($attendances, $fromIndex, 1));

        $i = 0;
        foreach ($attendances as $a) {
            $a->setSorting(++$i * 128);
        }
    }
}
