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

namespace Ferienpass\AdminBundle\MessageHandler;

use Ferienpass\AdminBundle\Message\HostInvite;
use Ferienpass\CoreBundle\Entity\Host;
use Ferienpass\CoreBundle\Entity\User;
use Ferienpass\CoreBundle\Notifier\Notifier;
use Ferienpass\CoreBundle\Repository\HostRepository;
use Ferienpass\CoreBundle\Repository\UserRepository;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
class WhenHostInviteThenNotify
{
    public function __construct(private readonly Notifier $notifier, private readonly HostRepository $hosts, private readonly UserRepository $users, private readonly UrlGeneratorInterface $urlGenerator, private readonly UriSigner $uriSigner)
    {
    }

    public function __invoke(HostInvite $message): void
    {
        /** @var User $user */
        $user = $this->users->find($message->getUserId());
        /** @var Host $host */
        $host = $this->hosts->find($message->getHostId());
        if (null === $user || null === $host) {
            return;
        }

        $notification = $this->notifier->userInvitation($user, $host, $message->getInviteeEmail());
        if (null === $notification) {
            return;
        }

        $this->notifier->send(
            $notification->actionUrl($this->uriSigner->sign($this->urlGenerator->generate('admin_invitation', ['email' => $message->getInviteeEmail(), 'host' => $host->getAlias()], UrlGeneratorInterface::ABSOLUTE_URL))),
            new Recipient($message->getInviteeEmail())
        );
    }
}
