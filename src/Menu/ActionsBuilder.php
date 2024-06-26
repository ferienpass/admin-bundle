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

namespace Ferienpass\AdminBundle\Menu;

use Ferienpass\AdminBundle\Controller\Page\AccountsController;
use Ferienpass\CoreBundle\Entity\AccessCodeStrategy;
use Ferienpass\CoreBundle\Entity\Attendance;
use Ferienpass\CoreBundle\Entity\Edition;
use Ferienpass\CoreBundle\Entity\Host;
use Ferienpass\CoreBundle\Entity\Offer\OfferInterface;
use Ferienpass\CoreBundle\Entity\Participant\ParticipantInterface;
use Ferienpass\CoreBundle\Entity\Payment;
use Ferienpass\CoreBundle\Entity\User;
use Ferienpass\CoreBundle\Repository\EditionRepository;
use Ferienpass\CoreBundle\Repository\PaymentRepository;
use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class ActionsBuilder
{
    public function __construct(private readonly FactoryInterface $factory, private readonly AuthorizationCheckerInterface $authorizationChecker, private readonly EditionRepository $editions, private readonly PaymentRepository $payments, private readonly EventDispatcherInterface $dispatcher)
    {
    }

    public function actions(array $options = []): ItemInterface
    {
        $item = $options['item'] ?? null;
        if (null === $item) {
            throw new \InvalidArgumentException('The list item is not available');
        }

        $menu = $this->factory->createItem('root');

        if ($item instanceof OfferInterface) {
            $this->offers($menu, $item);

            return $menu;
        }

        if ($item instanceof ParticipantInterface) {
            $this->participants($menu, $item);

            return $menu;
        }

        if ($item instanceof Host) {
            $this->hosts($menu, $item);

            return $menu;
        }

        if ($item instanceof Edition) {
            $this->editions($menu, $item);

            return $menu;
        }
        if ($item instanceof AccessCodeStrategy) {
            $this->accessCodes($menu, $item);

            return $menu;
        }

        if ($item instanceof Attendance) {
            $this->attendances($menu, $item);

            return $menu;
        }

        if ($item instanceof Payment) {
            $this->payments($menu, $item);

            return $menu;
        }

        if ($item instanceof User) {
            $this->accounts($menu, $item);

            return $menu;
        }

        return $menu;
    }

    private function offers(ItemInterface $root, OfferInterface $item)
    {
        $root->addChild('edit', [
            'label' => 'offers.action.edit',
            'route' => 'admin_offers_edit',
            'routeParameters' => array_filter(['id' => $item->getId(), 'edition' => $item->getEdition()?->getAlias()]),
            'display' => $this->isGranted('edit', $item),
            'extras' => ['icon' => 'pencil-solid'],
        ]);

        $root->addChild('proof', [
            'label' => 'offers.action.proof',
            'route' => 'admin_offers_proof',
            'routeParameters' => array_filter(['id' => $item->getId(), 'edition' => $item->getEdition()?->getAlias()]),
            'display' => $this->isGranted('view', $item),
            'extras' => ['icon' => 'pencil-solid'],
        ]);

        $root->addChild('newVariant', [
            'label' => 'offers.action.newVariant',
            'route' => 'admin_offers_new_variant',
            'routeParameters' => array_filter(['id' => null === $item->getVariantBase() ? $item->getId() : $item->getVariantBase()?->getId(), 'edition' => $item->getEdition()?->getAlias()]),
            'display' => $this->isGranted('create', $item) && $this->isGranted('edit', $item),
            'extras' => ['icon' => 'calendar-solid'],
        ]);

        if ($this->isGranted('ROLE_ADMIN')) {
            $root->addChild('copy', [
                'label' => 'offers.action.copy',
                'route' => 'admin_offers_copy',
                'routeParameters' => array_filter(['id' => $item->getId(), 'edition' => $item->getEdition()?->getAlias()]),
                'display' => $this->isGranted('view', $item),
                'extras' => ['icon' => 'duplicate-solid'],
            ]);
        } else {
            foreach ($this->editions->findWithActiveTask('host_editing_stage') as $edition) {
                $root->addChild('copy'.$edition->getId(), [
                    'label' => 'offers.action.copyTo',
                    'route' => 'admin_offers_copy',
                    'routeParameters' => ['id' => $item->getId(), 'edition' => $edition->getAlias()],
                    'display' => $this->isGranted('view', $item),
                    'extras' => ['icon' => 'duplicate-solid', 'translation_params' => ['edition' => $edition->getName()]],
                ]);
            }
        }

        if ($item->isOnlineApplication()) {
            $root->addChild('participantList', [
                'label' => 'offers.action.participants',
                'route' => 'admin_offer_participants',
                'routeParameters' => array_filter(['id' => $item->getId(), 'edition' => $item->getEdition()?->getAlias()]),
                'display' => $this->isGranted('participants.view', $item),
                'extras' => ['icon' => 'user-group-solid'],
            ]);
            $root->addChild('participantAssigning', [
                'label' => 'offers.action.assign',
                'route' => 'admin_offer_assign',
                'routeParameters' => array_filter(['id' => $item->getId(), 'edition' => $item->getEdition()?->getAlias()]),
                'display' => $this->isGranted('participants.view', $item) && ($item->getEdition()?->hostsCanAssign() || $this->isGranted('ROLE_ADMIN')),
                'extras' => ['icon' => 'user-group-solid'],
            ]);
        }

        $root->addChild('mailing', [
            'label' => 'offers.action.mailing',
            'route' => 'admin_tools_mailing',
            'routeParameters' => ['gruppe' => 'teilnehmende', 'angebote' => [$item->getId()]],
            'display' => $this->isGranted('participants.view', $item),
            'extras' => ['icon' => 'mail'],
        ]);

        $root->addChild('delete', [
            'label' => 'accounts.action.delete',
            'route' => 'admin_offers_delete',
            'routeParameters' => array_filter(['id' => $item->getId(), 'edition' => $item->getEdition()?->getAlias()]),
            'display' => $this->isGranted('delete', $item),
            'extras' => ['icon' => 'trash-solid', 'attr' => ['data-turbo-frame' => 'modal']],
        ]);
    }

    private function participants(ItemInterface $root, ParticipantInterface $item)
    {
        $root->addChild('attendances', [
            'label' => 'participants.action.attendances',
            'route' => 'admin_participants_attendances',
            'routeParameters' => ['id' => $item->getId()],
            'display' => $this->isGranted('view', $item),
            'extras' => ['icon' => 'pencil-solid'],
        ]);

        $root->addChild('edit', [
            'label' => 'participants.action.edit',
            'route' => 'admin_participants_edit',
            'routeParameters' => ['id' => $item->getId()],
            'display' => $this->isGranted('edit', $item),
            'extras' => ['icon' => 'pencil-solid'],
        ]);

        // TODO n+1 issue
        $payments = $this->payments->createQueryBuilder('pay')
            ->innerJoin('pay.items', 'i')
            ->innerJoin('i.attendance', 'a')
            ->innerJoin('a.participant', 'p')
            ->where('p.id = :id')
            ->setParameter('id', $item->getId())
            ->getQuery()
            ->getResult()
        ;

        /** @var Payment $payment */
        foreach ($payments as $payment) {
            $root->addChild('show_payment.'.$payment->getId(), [
                'label' => 'participants.action.show_payment',
                'route' => 'admin_payments_receipt',
                'routeParameters' => ['id' => $payment->getId()],
                // 'display' => $this->isGranted('view', $payment),
                'extras' => ['icon' => 'pencil-solid', 'translation_params' => ['%number%' => $payment->getReceiptNumber()]],
            ]);
        }

        $root->addChild('delete', [
            'label' => 'participants.action.delete',
            'route' => 'admin_participants_delete',
            'routeParameters' => array_filter(['id' => $item->getId()]),
            'display' => $this->isGranted('delete', $item),
            'extras' => ['icon' => 'trash-solid', 'attr' => ['data-turbo-frame' => 'modal']],
        ]);
    }

    private function hosts(ItemInterface $root, Host $item)
    {
        $root->addChild('edit', [
            'label' => 'hosts.action.edit',
            'route' => 'admin_hosts_edit',
            'routeParameters' => ['alias' => $item->getAlias()],
            'display' => $this->isGranted('edit', $item),
            'extras' => ['icon' => 'pencil-solid'],
        ]);

        $root->addChild('mailing', [
            'label' => 'hosts.action.mailing',
            'route' => 'admin_tools_mailing',
            'routeParameters' => ['gruppe' => 'veranstaltende', 'veranstaltende' => [$item->getId()]],
            'extras' => ['icon' => 'mail'],
        ]);

        $root->addChild('delete', [
            'label' => 'hosts.action.delete',
            'route' => 'admin_hosts_delete',
            'routeParameters' => array_filter(['alias' => $item->getAlias()]),
            'display' => $this->isGranted('delete', $item),
            'extras' => ['icon' => 'trash-solid', 'attr' => ['data-turbo-frame' => 'modal']],
        ]);
    }

    private function editions(ItemInterface $root, Edition $item)
    {
        $root->addChild('edit', [
            'label' => 'editions.action.edit',
            'route' => 'admin_editions_edit',
            'routeParameters' => ['alias' => $item->getAlias()],
            'display' => $this->isGranted('edit', $item),
            'extras' => ['icon' => 'pencil-solid'],
        ]);

        $root->addChild('stats', [
            'label' => 'editions.action.stats',
            'route' => 'admin_editions_stats',
            'routeParameters' => ['alias' => $item->getAlias()],
            'display' => $this->isGranted('stats', $item),
            'extras' => ['icon' => 'chart-pie.mini'],
        ]);

        $root->addChild('assign', [
            'label' => 'editions.action.assign',
            'route' => 'admin_editions_assign',
            'routeParameters' => ['alias' => $item->getAlias()],
            'display' => $this->isGranted('assign', $item),
            'extras' => ['icon' => 'arrow-path-rounded-square.mini'],
        ]);

        $root->addChild('delete', [
            'label' => 'editions.action.delete',
            'route' => 'admin_editions_delete',
            'routeParameters' => array_filter(['alias' => $item->getAlias()]),
            'display' => $this->isGranted('delete', $item),
            'extras' => ['icon' => 'trash-solid', 'attr' => ['data-turbo-frame' => 'modal']],
        ]);
    }

    private function accessCodes(ItemInterface $root, AccessCodeStrategy $item)
    {
        $root->addChild('edit', [
            'label' => 'accessCodes.action.edit',
            'route' => 'admin_accessCodes_edit',
            'routeParameters' => ['id' => $item->getId()],
            // 'display' => $this->isGranted('edit', $item),
            'extras' => ['icon' => 'pencil-solid'],
        ]);

        $root->addChild('delete', [
            'label' => 'accessCodes.action.delete',
            'route' => 'admin_accessCodes_delete',
            'routeParameters' => array_filter(['id' => $item->getId()]),
            'display' => $this->isGranted('delete', $item),
            'extras' => ['icon' => 'trash-solid', 'attr' => ['data-turbo-frame' => 'modal']],
        ]);
    }

    private function attendances(ItemInterface $root, Attendance $item)
    {
        $root->addChild('offer', [
            'label' => 'attendance.action.offer',
            'route' => 'admin_offer_assign',
            'routeParameters' => ['id' => $item->getOffer()->getId(), 'edition' => $item->getOffer()->getEdition()->getAlias()],
            'display' => $this->isGranted('view', $item->getOffer()),
        ]);
    }

    private function payments(ItemInterface $root, Payment $item)
    {
        $root->addChild('receipt', [
            'label' => 'payments.action.receipt',
            'route' => 'admin_payments_receipt',
            'routeParameters' => ['id' => $item->getId()],
            'extras' => ['icon' => 'pencil-solid'],
        ]);
    }

    private function accounts(ItemInterface $root, User $item)
    {
        $root->addChild('edit', [
            'label' => 'accounts.action.edit',
            'route' => 'admin_accounts_edit',
            'routeParameters' => ['id' => $item->getId(), 'role' => array_search($item->getRoles()[0] ?? 'ROLE_USER', AccountsController::ROLES, true) ?: 'eltern'],
            'display' => $this->isGranted('edit', $item),
            'extras' => ['icon' => 'pencil-solid'],
        ]);

        if (\in_array('ROLE_MEMBER', $item->getRoles(), true)) {
            $root->addChild('impersonate', [
                'label' => 'accounts.action.impersonate',
                'route' => 'admin_frontend_preview',
                'routeParameters' => ['username' => $item->getUserIdentifier()],
                'display' => $this->isGranted('ROLE_ALLOWED_TO_SWITCH'),
                'linkAttributes' => ['data-turbo' => 'false'],
                'extras' => ['icon' => 'logout-filled', 'translation_params' => ['user' => $item->getUserIdentifier()]],
            ]);
        } else {
            $root->addChild('impersonate', [
                'label' => 'accounts.action.impersonate',
                'route' => 'admin_index',
                'routeParameters' => ['_switch_user' => $item->getUserIdentifier()],
                'display' => $this->isGranted('ROLE_ALLOWED_TO_SWITCH'),
                'linkAttributes' => ['data-turbo' => 'false'],
                'extras' => ['icon' => 'logout-filled', 'translation_params' => ['user' => $item->getUserIdentifier()]],
            ]);
        }

        $root->addChild('password', [
            'label' => 'accounts.action.password',
            'route' => 'admin_accounts_password',
            'routeParameters' => array_filter(['id' => $item->getId()]),
            'display' => $this->isGranted('password', $item),
            'extras' => ['icon' => 'key.solid', 'attr' => ['data-turbo-frame' => 'modal']],
        ]);

        $root->addChild('delete', [
            'label' => 'accounts.action.delete',
            'route' => 'admin_accounts_delete',
            'routeParameters' => array_filter(['id' => $item->getId()]),
            'display' => $this->isGranted('delete', $item),
            'extras' => ['icon' => 'trash-solid', 'attr' => ['data-turbo-frame' => 'modal']],
        ]);
    }

    private function isGranted(string $attribute, object $item = null): bool
    {
        return $this->authorizationChecker->isGranted($attribute, $item);
    }
}
