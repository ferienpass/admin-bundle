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

namespace Ferienpass\AdminBundle\Controller\Dashboard;

use Ferienpass\CoreBundle\Facade\EraseDataFacade;
use Ferienpass\CoreBundle\Repository\EditionRepository;
use Ferienpass\CoreBundle\Repository\OfferRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class NextStepsController extends AbstractController
{
    public function __construct()
    {
    }

    public function __invoke(OfferRepositoryInterface $offers, EditionRepository $editions, EraseDataFacade $eraseData): Response
    {
        return $this->render('@FerienpassAdmin/fragment/dashboard/next_steps.html.twig', [
            'editions' => $editions,
            'offers' => $offers,
            'expiredParticipants' => $eraseData->expiredParticipants(),
        ]);
    }
}
