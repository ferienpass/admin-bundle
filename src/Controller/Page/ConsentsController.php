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
use Ferienpass\AdminBundle\Form\Filter\ConsentsFilter;
use Ferienpass\CoreBundle\Repository\ConsentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/einwilligungen')]
final class ConsentsController extends AbstractController
{
    #[Route('', name: 'admin_consents_index')]
    public function index(ConsentRepository $repository, Breadcrumb $breadcrumb): Response
    {
        $qb = $repository->createQueryBuilder('i');
        $qb->orderBy('i.signedAt', 'DESC');

        return $this->render('@FerienpassAdmin/page/consents/index.html.twig', [
            'qb' => $qb,
            'filterType' => ConsentsFilter::class,
            'searchable' => [],
            'breadcrumb' => $breadcrumb->generate('consents.title'),
        ]);
    }
}
