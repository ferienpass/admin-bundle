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
use Ferienpass\AdminBundle\Form\EditAccessCodesType;
use Ferienpass\CoreBundle\Entity\AccessCodeStrategy;
use Ferienpass\CoreBundle\Entity\User;
use Ferienpass\CoreBundle\Facade\EraseDataFacade;
use Ferienpass\CoreBundle\Repository\AccessCodeStrategyRepository;
use Ferienpass\CoreBundle\Repository\HostConsentRepository;
use Ferienpass\CoreBundle\Session\Flash;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

final class ToolsController extends AbstractController
{
    #[Route('/tools', name: 'admin_tools')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(Breadcrumb $breadcrumb): Response
    {
        return $this->render('@FerienpassAdmin/page/tools/index.html.twig', [
            'breadcrumb' => $breadcrumb->generate('tools.title'),
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/export', name: 'admin_export_index')]
    public function export(Breadcrumb $breadcrumb): Response
    {
        return $this->render('@FerienpassAdmin/page/tools/export.html.twig', [
            'breadcrumb' => $breadcrumb->generate(['tools.title', ['route' => 'admin_tools']], 'export.title'),
        ]);
    }

    #[Route('/rundmail', name: 'admin_tools_mailing')]
    public function mailing(#[CurrentUser] User $user, Breadcrumb $breadcrumb, HostConsentRepository $consents): Response
    {
        if (null === $consents->findValid($user)) {
            return $this->render('@FerienpassAdmin/page/missing_privacy_statement.html.twig', [
                'breadcrumb' => $breadcrumb->generate(['tools.title', ['route' => 'admin_tools']], 'mailing.title'),
            ]);
        }

        return $this->render('@FerienpassAdmin/page/tools/mailing.html.twig', [
            'breadcrumb' => $breadcrumb->generate(['tools.title', ['route' => 'admin_tools']], 'mailing.title'),
        ]);
    }

    #[Route('/betroffenenrechte', name: 'admin_tools_subjectrights')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function subjectRights(Breadcrumb $breadcrumb): Response
    {
        return $this->render('@FerienpassAdmin/page/tools/noop.html.twig', [
            'breadcrumb' => $breadcrumb->generate(['tools.title', ['route' => 'admin_tools']], 'subjectrichts.title'),
        ]);
    }

    #[Route('/system', name: 'admin_tools_settings')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function settings(): Response
    {
        return $this->redirectToRoute('admin_tools_settings_export');
    }

    #[Route('/system/angebotskategorien', name: 'admin_tools_settings_categories')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function settingsCategories(Breadcrumb $breadcrumb): Response
    {
        return $this->render('@FerienpassAdmin/page/tools/settings_categories.html.twig', [
            'breadcrumb' => $breadcrumb->generate(['tools.title', ['route' => 'admin_tools']], 'settings.title', 'offerCategories.title'),
        ]);
    }

    #[Route('/system/export', name: 'admin_tools_settings_export')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function settingsExports(Breadcrumb $breadcrumb): Response
    {
        return $this->render('@FerienpassAdmin/page/tools/settings_export.html.twig', [
            'breadcrumb' => $breadcrumb->generate(['tools.title', ['route' => 'admin_tools']], 'settings.title', 'export.title'),
        ]);
    }

    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[Route('/postausgang', name: 'admin_outbox')]
    public function outbox(Breadcrumb $breadcrumb): Response
    {
        return $this->render('@FerienpassAdmin/page/tools/outbox.html.twig', [
            'breadcrumb' => $breadcrumb->generate(['tools.title', ['route' => 'admin_tools']], 'outbox.title'),
        ]);
    }

    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[Route('/daten-löschen', name: 'admin_erase_data')]
    public function eraseData(EraseDataFacade $eraseDataFacade, Breadcrumb $breadcrumb, Request $request): Response
    {
        $participants = $eraseDataFacade->expiredParticipants();

        $form = $this->createFormBuilder()->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $eraseDataFacade->eraseData();

            return $this->redirectToRoute('admin_erase_data');
        }

        return $this->render('@FerienpassAdmin/page/tools/erase_data.html.twig', [
            'form' => $form,
            'participants' => $participants,
            'breadcrumb' => $breadcrumb->generate(['tools.title', ['route' => 'admin_tools']], 'eraseData.title'),
        ]);
    }

    #[Route('/system/zugangscodes', name: 'admin_accessCodes_index')]
    public function accessCodes(Breadcrumb $breadcrumb, AccessCodeStrategyRepository $repository): Response
    {
        $qb = $repository->createQueryBuilder('i');

        $qb->addOrderBy('i.name', 'ASC');

        return $this->render('@FerienpassAdmin/page/tools/access_codes.html.twig', [
            'qb' => $qb,
            'createUrl' => $this->generateUrl('admin_accessCodes_create'),
            'breadcrumb' => $breadcrumb->generate(['tools.title', ['route' => 'admin_tools']], 'settings.title', 'accessCodes.title'),
        ]);
    }

    #[Route('/einstellungen/zugangscodes/neu', name: 'admin_accessCodes_create')]
    #[Route('/einstellungen/zugangscodes/{id}', name: 'admin_accessCodes_edit')]
    public function edit(?AccessCodeStrategy $accessCodeStrategy, Request $request, EntityManagerInterface $em, Breadcrumb $breadcrumb, Flash $flash): Response
    {
        $accessCodeStrategy ??= new AccessCodeStrategy();

        $form = $this->createForm(EditAccessCodesType::class, $accessCodeStrategy);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$em->contains($accessCodeStrategy = $form->getData())) {
                $em->persist($accessCodeStrategy);
            }

            $em->flush();

            $flash->addConfirmation(text: new TranslatableMessage('editConfirm', domain: 'admin'));

            return $this->redirectToRoute('admin_accessCodes_edit', ['id' => $accessCodeStrategy->getId()]);
        }

        $breadcrumbTitle = $accessCodeStrategy ? $accessCodeStrategy->getName().' (bearbeiten)' : 'accessCodes.new';

        /** @noinspection FormViewTemplate `createView()` messes ups error handling/redirect */
        return $this->render('@FerienpassAdmin/page/tools/access_codes_edit.html.twig', [
            'item' => $accessCodeStrategy,
            'form' => $form,
            'breadcrumb' => $breadcrumb->generate(['tools.title', ['route' => 'admin_tools']], 'settings.title', 'accessCodes.title', $breadcrumbTitle),
        ]);
    }

    #[Route('/einstellungen/zugangscodes/{id}/löschen', name: 'admin_accessCodes_delete', requirements: ['id' => '\d+'])]
    public function delete(AccessCodeStrategy $item, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('delete', $item);

        $form = $this->createForm(FormType::class, options: [
            'action' => $this->generateUrl('admin_participants_delete', ['id' => $item->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->remove($item);
            $entityManager->flush();
        }

        return $this->render('@FerienpassAdmin/page/tools/access_codes_delete.html.twig', [
            'item' => $item,
            'form' => $form,
        ]);
    }

    #[Route('/tools/schnellerfassung', name: 'admin_tools_registration')]
    #[IsGranted('ROLE_ADMIN')]
    public function quickRegistration(Breadcrumb $breadcrumb): Response
    {
        return $this->render('@FerienpassAdmin/page/tools/quick_registration.html.twig', [
            'breadcrumb' => $breadcrumb->generate(['tools.title', ['route' => 'admin_tools']], 'Schnell-Erfassung'),
        ]);
    }

    #[Route('/tools/problem-melden', name: 'admin_tools_issue')]
    #[IsGranted('ROLE_ADMIN')]
    public function createIssue(Breadcrumb $breadcrumb): Response
    {
        return $this->render('@FerienpassAdmin/page/tools/create_issue.html.twig', [
            'breadcrumb' => $breadcrumb->generate(['tools.title', ['route' => 'admin_tools']], 'Ticket erstellen'),
        ]);
    }

    #[Route('/download/{file}', name: 'admin_download')]
    public function download(string $file, UriSigner $uriSigner, Request $request): BinaryFileResponse
    {
        if (!$uriSigner->checkRequest($request)) {
            throw $this->createNotFoundException();
        }

        return $this->file(base64_decode($file, true));
    }
}
