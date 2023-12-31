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

use Ferienpass\AdminBundle\Dto\AddAttendanceDto;
use Ferienpass\AdminBundle\Form\ParticipantAddAttendanceType;
use Ferienpass\CoreBundle\Entity\Offer;
use Ferienpass\CoreBundle\Entity\Participant;
use Ferienpass\CoreBundle\Facade\AttendanceFacade;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(name: 'AddAttendance', template: '@FerienpassAdmin/components/AddAttendance.html.twig')]
class AddAttendance extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    #[LiveProp]
    public Offer|null $offer = null;
    #[LiveProp]
    public Participant|null $participant = null;

    public function __construct(private FormFactoryInterface $formFactory)
    {
    }

    #[LiveAction]
    public function preview(AttendanceFacade $attendanceFacade)
    {
        if (!$this->offer || !$this->participant) {
            return;
        }

        $preview = $attendanceFacade->preview($this->offer, $this->participant);

        $this->formValues['status'] = $preview->getStatus();
    }

    #[LiveAction]
    public function submit(AttendanceFacade $attendanceFacade)
    {
        $this->submitForm();

        /** @var AddAttendanceDto $dto */
        $dto = $this->getFormInstance()->getData();
        $attendanceFacade->create($dto->getOffer(), $dto->getParticipant(), $dto->getStatus(), $dto->shallNotify());

        return $this->redirectToRoute('admin_participants_attendances', ['id' => $this->participant->getId()]);
    }

    protected function instantiateForm(): FormInterface
    {
        return $this->formFactory->create(ParticipantAddAttendanceType::class, new AddAttendanceDto($this->participant, $this->offer));
    }
}
