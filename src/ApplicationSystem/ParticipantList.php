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

namespace Ferienpass\AdminBundle\ApplicationSystem;

use Doctrine\ORM\EntityManagerInterface;
use Ferienpass\CoreBundle\Entity\Attendance;
use Ferienpass\CoreBundle\Message\AttendanceStatusChanged;
use Ferienpass\CoreBundle\Message\ParticipantListChanged;
use Symfony\Component\Messenger\MessageBusInterface;

class ParticipantList
{
    public function __construct(private readonly MessageBusInterface $messageBus, private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param Attendance[] $attendances
     */
    public function confirm(array $attendances, bool $reorder = false, bool $notify = true): void
    {
        foreach ($attendances as $attendance) {
            if (null === $attendance->getParticipant()) {
                continue;
            }

            $oldStatus = $attendance->getStatus();
            if (Attendance::STATUS_CONFIRMED === $oldStatus) {
                continue;
            }

            $attendance->setStatus(Attendance::STATUS_CONFIRMED);

            $this->messageBus->dispatch(new AttendanceStatusChanged($attendance->getId(), $oldStatus, $attendance->getStatus(), $notify));
        }

        $this->entityManager->flush();

        if (false === $reorder) {
            return;
        }

        foreach (array_unique(array_map(fn (Attendance $a) => $a->getOffer()->getId(), $attendances)) as $offerId) {
            $this->messageBus->dispatch(new ParticipantListChanged($offerId));
        }
    }

    /**
     * @param Attendance[] $attendances
     */
    public function reject(array $attendances, bool $reorder = false, bool $notify = true): void
    {
        foreach ($attendances as $attendance) {
            if (null === $attendance->getParticipant()) {
                continue;
            }

            $oldStatus = $attendance->getStatus();

            if (Attendance::STATUS_WITHDRAWN === $oldStatus) {
                continue;
            }

            $attendance->setStatus(Attendance::STATUS_WITHDRAWN);

            $this->messageBus->dispatch(new AttendanceStatusChanged($attendance->getId(), $oldStatus, $attendance->getStatus(), $notify));
        }

        $this->entityManager->flush();

        if (false === $reorder) {
            return;
        }

        foreach (array_unique(array_map(fn (Attendance $a) => $a->getOffer()->getId(), $attendances)) as $offerId) {
            $this->messageBus->dispatch(new ParticipantListChanged($offerId));
        }
    }
}
