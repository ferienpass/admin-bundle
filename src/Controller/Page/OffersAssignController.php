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

use Ferienpass\AdminBundle\Breadcrumb\Breadcrumb;
use Ferienpass\CoreBundle\Entity\Edition;
use Ferienpass\CoreBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class OffersAssignController extends AbstractController
{
    #[Route('/saisons/{alias}/zuordnen', name: 'admin_editions_assign')]
    #[Route('/angebote/zuordnen', name: 'admin_offers_assign', priority: 2)]
    public function __invoke(?Edition $edition, #[CurrentUser] User $user, Request $request, Breadcrumb $breadcrumb): Response
    {
        /** @noinspection FormViewTemplate `createView()` messes ups error handling/redirect */
        return $this->render('@FerienpassAdmin/page/edition/assign.html.twig', [
            'edition' => $edition,
            'breadcrumb' => $breadcrumb->generate(['tools.title', ['route' => 'admin_tools']], $edition?->getName() ?? '', 'Plätze zuteilen'),
        ]);
    }
}
