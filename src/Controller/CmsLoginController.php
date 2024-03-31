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
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/cms', name: 'admin_cms')]
#[IsGranted('ROLE_CMS_ADMIN')]
class CmsLoginController extends AbstractController
{
    public function __construct(#[Autowire(service: 'ferienpass.security.contao_backend_user_provider')] private readonly UserProviderInterface $userProvider)
    {
    }

    public function __invoke(#[CurrentUser] User $user, Request $request): Response
    {
        $session = $request->getSession();
        if ($session->has('_security_contao_backend')) {
            return $this->redirectToRoute('contao_backend', status: 303);
        }

        $contaoUser = $this->userProvider->loadUserByIdentifier($user->getUserIdentifier());

        $token = new UsernamePasswordToken($contaoUser, 'contao_backend', $contaoUser->getRoles());
        $session->set('_security_contao_backend', serialize($token));

        return $this->redirectToRoute('contao_backend', status: 303);
    }
}
