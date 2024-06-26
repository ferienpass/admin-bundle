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
use Ferienpass\AdminBundle\Dto\AddAttendanceDto;
use Ferienpass\AdminBundle\Form\AddAttendanceType;
use Ferienpass\CoreBundle\Entity\Attendance;
use Ferienpass\CoreBundle\Entity\Offer\OfferInterface;
use Ferienpass\CoreBundle\Entity\Participant\ParticipantInterface;
use Ferienpass\CoreBundle\Facade\AttendanceFacade;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(route: 'live_component_admin')]
class AddAttendance extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    #[LiveProp]
    public OfferInterface|null $offer = null;

    #[LiveProp]
    public ParticipantInterface|null $participant = null;

    #[LiveProp]
    public bool $isNewParticipant = false;

    public ?Attendance $success = null;

    public function __construct(private readonly FormFactoryInterface $formFactory)
    {
    }

    #[LiveAction]
    public function newParticipant(): void
    {
        $this->isNewParticipant = true;
    }

    #[LiveAction]
    public function preview(AttendanceFacade $attendanceFacade): void
    {
        // $this->submitForm(false);
        $this->success = null;

        /** @var AddAttendanceDto $dto */
        $dto = $this->getForm()->getData();

        if (!$dto->getOffer() || !$dto->getParticipant()) {
            return;
        }

        $preview = $attendanceFacade->preview($dto->getOffer(), $dto->getParticipant());

        $this->formValues['status'] = $preview->getStatus();
    }

    #[LiveAction]
    public function submit(AttendanceFacade $attendanceFacade, EntityManagerInterface $entityManager): void
    {
        $this->submitForm();

        /** @var AddAttendanceDto $dto */
        $dto = $this->getForm()->getData();

        if ($this->isNewParticipant) {
            $entityManager->persist($dto->getParticipant());
        }

        $this->success = $attendanceFacade->create($dto->getOffer(), $dto->getParticipant(), $dto->getStatus(), $dto->shallNotify());
        $this->isNewParticipant = false;

        $this->resetForm();
    }

    protected function instantiateForm(): FormInterface
    {
        return $this->formFactory->create(AddAttendanceType::class, new AddAttendanceDto($this->participant, $this->offer), [
            'add_participant' => null === $this->participant,
            'add_offer' => null === $this->offer,
            'new_participant' => $this->isNewParticipant ?? false,
        ]);
    }
}
