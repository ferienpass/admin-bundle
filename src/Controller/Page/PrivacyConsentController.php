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
use Ferienpass\CoreBundle\Entity\HostConsent;
use Ferienpass\CoreBundle\Entity\User;
use Ferienpass\CoreBundle\Repository\HostConsentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/datenschutz-einwilligung', name: 'admin_privacy_consent')]
class PrivacyConsentController extends AbstractController
{
    public function __invoke(#[CurrentUser] User $user, HostConsentRepository $consents, Breadcrumb $breadcrumb, Request $request, #[Autowire(param: 'ferienpass_admin.privacy_consent_text')] string $consentText, EntityManagerInterface $em): Response
    {
        $consent = $consents->findValid($user);
        if (null !== $consent) {
            return $this->render('@FerienpassAdmin/page/profile/privacy_consent.html.twig', [
                'consent' => $consent,
                'consentText' => $consentText,
                'breadcrumb' => $breadcrumb->generate('profile.title', 'privacyConsent.title'),
            ]);
        }

        $form = $this->createFormBuilder()->add('submit', SubmitType::class, ['label' => 'Unterzeichnen'])->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $consent = HostConsent::fromText($consentText, $user);

            $em->persist($consent);
            $em->flush();

            return $this->redirect($request->getRequestUri());
        }

        /** @noinspection FormViewTemplate `createView()` messes ups error handling/redirect */
        return $this->render('@FerienpassAdmin/page/profile/privacy_consent.html.twig', [
            'form' => $form,
            'consentText' => $consentText,
            'breadcrumb' => $breadcrumb->generate('profile.title', 'privacyConsent.title'),
        ]);
    }
}
