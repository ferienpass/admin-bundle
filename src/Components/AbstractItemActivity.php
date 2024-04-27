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
use Ferienpass\CoreBundle\Entity\OfferLog;
use Ferienpass\CoreBundle\Entity\Participant\ParticipantInterface;
use Ferienpass\CoreBundle\Entity\ParticipantLog;
use Ferienpass\CoreBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ValidatableComponentTrait;

abstract class AbstractItemActivity extends AbstractController
{
    use ComponentToolsTrait;
    use DefaultActionTrait;
    use ValidatableComponentTrait;

    #[LiveProp(writable: true)]
    #[NotBlank]
    public string $newComment = '';

    #[LiveAction]
    public function comment(EntityManagerInterface $em): void
    {
        $this->validate();

        $user = $this->getUser();
        if (!$user instanceof User) {
            return;
        }

        //        $comment = match (true) {
        //            $this->item instanceof Attendance => new ParticipantLog($this->item->getParticipant(), $user, attendance: $this->item, comment: $this->newComment),
        //            $this->item instanceof ParticipantInterface => new ParticipantLog($this->item, $user, comment: $this->newComment),
        //            $this->item instanceof OfferInterface => new OfferLog($this->item, $user, comment: $this->newComment),
        //            default => null,
        //        };

        $comment = $this->createComment($user, $this->newComment);

        $em->persist($comment);
        $em->flush();

        $this->newComment = '';
        $this->resetValidation();

        $this->emit('admin_list:changed');

        // $this->addFlash('success', 'Post saved!');
    }

    abstract protected function createComment(User $user, string $comment): object;
}
