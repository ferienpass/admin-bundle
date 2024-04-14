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

namespace Ferienpass\AdminBundle\State;

use Ferienpass\CoreBundle\Entity\Attendance;
use Ferienpass\CoreBundle\Repository\AttendanceRepository;

class SystemStatus
{
    public function __construct(private readonly AttendanceRepository $attendanceRepository)
    {
    }

    public function hasWarnings(): bool
    {
        return !empty($this->generateWarnings());
    }

    public function generateWarnings()
    {
        $unassignedParticipants = $this->attendanceRepository->createQueryBuilder('attendance')
            ->innerJoin('attendance.offer', 'offer')
            ->innerJoin('offer.edition', 'edition')
            ->innerJoin('offer.dates', 'dates')
            ->where("attendance.status = '".Attendance::STATUS_WAITING."'")
            ->groupBy('attendance.id')
            ->addGroupBy('edition')
            ->andWhere('dates.begin > CURRENT_TIMESTAMP()')
            ->getQuery()
            ->execute()
        ;

        $warnings = [];
        /** @var Attendance $a */
        foreach ($unassignedParticipants as $a) {
            $warnings[$a->getOffer()->getEdition()->getId()][] = $a;
        }

        return $warnings;
    }
}
