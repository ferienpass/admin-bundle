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

namespace Ferienpass\AdminBundle\Controller;

use Ferienpass\CoreBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/vorschau')]
#[IsGranted('ROLE_ADMIN')]
class FrontendPreviewController extends AbstractController
{
    public function __construct(#[Autowire(service: 'security.user.provider.concrete.ferienpass_user_provider')] private readonly UserProviderInterface $userProvider)
    {
    }

    #[Route('/{username?}', name: 'admin_frontend_preview')]
    public function __invoke(?string $username, Request $request): Response
    {
        if (null !== $username) {
            $this->denyAccessUnlessGranted('ROLE_ALLOWED_TO_SWITCH');

            if (!(($frontendUser = $this->userProvider->loadUserByIdentifier($username)) instanceof User)) {
                throw $this->createNotFoundException();
            }

            $token = new UsernamePasswordToken($frontendUser, 'contao_frontend', $frontendUser->getRoles());

            $session = $request->getSession();
            $session->set('_security_contao_frontend', serialize($token));
        }

        return $this->redirect('/', 303);
    }
}
