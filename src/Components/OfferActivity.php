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

use Ferienpass\CoreBundle\Entity\Offer\OfferInterface;
use Ferienpass\CoreBundle\Entity\OfferLog;
use Ferienpass\CoreBundle\Entity\User;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsLiveComponent(template: '@FerienpassAdmin/components/ItemActivity.html.twig', route: 'live_component_admin')]
class OfferActivity extends AbstractItemActivity
{
    #[LiveProp]
    public OfferInterface $item;

    #[ExposeInTemplate]
    public function activity()
    {
        return $this->item->getActivity()->toArray();
    }

    protected function createComment(User $user, string $comment): object
    {
        return new OfferLog($this->item, $user, $comment);
    }
}
