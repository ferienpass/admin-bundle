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

use Ferienpass\CoreBundle\Entity\User;
use Ferienpass\CoreBundle\Notification\MailingNotification;
use Ferienpass\CoreBundle\Notifier\Notifier;
use Ferienpass\CoreBundle\Repository\EditionRepository;
use Ferienpass\CoreBundle\Repository\HostRepository;
use Ferienpass\CoreBundle\Repository\OfferRepositoryInterface;
use Ferienpass\CoreBundle\Repository\ParticipantRepositoryInterface;
use Ferienpass\CoreBundle\Repository\UserRepository;
use Ferienpass\CoreBundle\Session\Flash;
use League\CommonMark\CommonMarkConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ValidatableComponentTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;
use Twig\Environment;
use Twig\Error\Error;

#[AsLiveComponent(route: 'live_component_admin')]
class Mailing extends AbstractController
{
    use ComponentToolsTrait;
    use DefaultActionTrait;
    use ValidatableComponentTrait;

    #[LiveProp(writable: true, url: true)]
    public ?string $group = null;

    #[LiveProp(writable: true)]
    public bool $hostsNotDisabled = true;

    #[LiveProp(writable: true)]
    public bool $hostsWithOffer = false;

    #[LiveProp(writable: true)]
    public array $selectedEditions = [];

    #[LiveProp(writable: true, onUpdated: 'onOffersUpdated', url: true)]
    public array $selectedOffers = [];

    #[LiveProp(writable: true, url: true)]
    public array $selectedHosts = [];

    #[LiveProp(writable: true)]
    #[Assert\NotBlank()]
    public string $emailSubject = '';

    #[LiveProp(writable: true)]
    #[Assert\NotBlank()]
    public string $emailText = '';

    #[LiveProp(writable: true)]
    public array $attendanceStatus = [];

    public function __construct(private readonly EditionRepository $editions, private readonly ParticipantRepositoryInterface $participants, private readonly UserRepository $users, private readonly HostRepository $hosts, private readonly OfferRepositoryInterface $offers, private readonly Environment $twig, private readonly RequestStack $requestStack, private readonly NormalizerInterface $normalizer, private readonly Notifier $notifier, private readonly MailingNotification $mailingNotification)
    {
    }

    public function mount()
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->group = 'participants';
        }
    }

    #[LiveListener('group')]
    public function changeGroup(#[LiveArg] string $group)
    {
        $this->group = $group;
    }

    public function onOffersUpdated($previous): void
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }

        $this->selectedOffers = $previous;
    }

    #[ExposeInTemplate]
    public function countAllHosts()
    {
        $qb = $this->users->createQueryBuilder('u')
            ->innerJoin('u.hostAssociations', 'ha')
            ->innerJoin('ha.host', 'host')
        ;

        $qb->select('COUNT(DISTINCT u.email)');

        return $qb->getQuery()->getSingleScalarResult();
    }

    #[ExposeInTemplate]
    public function countAllParticipants()
    {
        $qb = $this->participants->createQueryBuilder('p')
            ->innerJoin('p.attendances', 'attendances')
            ->innerJoin('attendances.offer', 'offer')
            ->leftJoin('p.user', 'u')
        ;

        $qb->select('COUNT(DISTINCT COALESCE(p.email, u.email))');

        return $qb->getQuery()->getSingleScalarResult();
    }

    #[ExposeInTemplate]
    public function context(): array
    {
        $context = [];

        $context['baseUrl'] = $this->requestStack->getCurrentRequest()?->getSchemeAndHttpHost().$this->requestStack->getCurrentRequest()?->getBaseUrl();

        if (1 === \count($this->selectedEditions)) {
            $context['edition'] = $this->editions->find(array_values($this->selectedEditions)[0]);
        }
        if (1 === \count($this->selectedOffers)) {
            $context['offer'] = $this->offers->find(array_values($this->selectedOffers)[0]);
        }
        if (1 === \count($this->selectedHosts)) {
            $context['host'] = $this->hosts->find(array_values($this->selectedHosts)[0]);
        }

        return $context;
    }

    #[ExposeInTemplate]
    public function editionOptions()
    {
        return $this->editions->findBy(['archived' => 0]);
    }

    #[ExposeInTemplate]
    public function offerOptions()
    {
        $qb = $this->offers->createQueryBuilder('o')
            ->where('o.id IN (:ids)')
            ->setParameter('ids', $this->selectedOffers);

        if (!$this->isGranted('ROLE_ADMIN')) {
            $user = $this->getUser();
            $qb->innerJoin('o.hosts', 'hosts')->andWhere('hosts IN (:hosts)')->setParameter('hosts', $user instanceof User ? $user->getHosts() : []);
        }

        return $qb->getQuery()->getResult();
    }

    #[ExposeInTemplate]
    public function hostOptions()
    {
        $qb = $this->hosts->createQueryBuilder('h')
            ->where('h.id IN (:ids)')
            ->setParameter('ids', $this->selectedHosts)
        ;

        if (!$this->isGranted('ROLE_ADMIN')) {
            $user = $this->getUser();
            $qb->andWhere('h.id IN (:hosts)')->setParameter('hosts', $user instanceof User ? $user->getHosts() : []);
        }

        return $qb->getQuery()->getResult();
    }

    #[ExposeInTemplate]
    public function recipients()
    {
        $return = [];

        if ('hosts' === $this->group) {
            foreach ($this->queryHostAccounts() as $item) {
                $return[$item->getEmail()][] = $item;
            }
        } elseif ('participants' === $this->group) {
            foreach ($this->queryParticipants() as $item) {
                $return[$item->getEmail()][] = $item;
            }
        }

        return $return;
    }

    #[ExposeInTemplate]
    public function preview(): string|false
    {
        try {
            return $this->twig->createTemplate($this->parsedEmailText())->render($this->context());
        } catch (Error) {
            return false;
        }
    }

    #[ExposeInTemplate]
    public function availableTokens()
    {
        $availableTokens = [];

        foreach ($this->context() as $token => $object) {
            switch ($token) {
                case 'baseUrl':
                    $availableTokens[$token] = $object;
                    break;
                case 'edition':
                case 'offer':
                case 'host':
                    $tokens = $this->normalizer->normalize($object, context: ['groups' => 'notification']);
                    foreach (array_keys($tokens) as $property) {
                        $availableTokens["$token.$property"] = $this->container->get('twig')->createTemplate(sprintf('{{ %s }}', "$token.$property"))->render([$token => $tokens]);
                    }
                    break;
            }
        }

        return $availableTokens;
    }

    #[LiveAction]
    public function submit()
    {
        $this->validate();
        $this->dispatchBrowserEvent('admin:modal:open');
    }

    #[LiveAction]
    public function send(Flash $flash)
    {
        foreach (array_keys($this->recipients()) as $email) {
            $notification = clone $this->mailingNotification;
            $notification->subject($this->emailSubject);
            $notification->content($this->parsedEmailText());
            $notification->context($this->context());

            $this->notifier->send($notification, new Recipient($email));
        }

        $flash->addConfirmation('Versand erfolgreich', 'Die E-Mails wurden versandt.');

        $this->emailSubject = '';
        $this->emailText = '';

        $this->dispatchBrowserEvent('admin:modal:close');
        $this->resetValidation();
    }

    #[LiveAction]
    public function cancel()
    {
        $this->dispatchBrowserEvent('admin:modal:close');
    }

    private function queryHostAccounts()
    {
        $qb = $this->users
            ->createQueryBuilder('u')
            ->innerJoin('u.hostAssociations', 'ha')
            ->innerJoin('ha.host', 'host')
        ;

        if ($this->hostsNotDisabled) {
            $qb->andWhere('u.disable = :disabled')->setParameter('disabled', 0);
        }

        if ($this->hostsWithOffer) {
            $qb->innerJoin('host.offers', 'offers');
            $qb->leftJoin('offers.edition', 'edition');

            if ($this->selectedEditions) {
                $qb->andWhere('edition IN (:editions)')->setParameter('editions', $this->selectedEditions);
            }
        }

        if ($this->selectedHosts) {
            $qb->andWhere('host IN (:hosts)')->setParameter('hosts', $this->selectedHosts);
        }

        return $qb->getQuery()->getResult();
    }

    private function parsedEmailText(): string
    {
        $converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return (string) $converter->convert($this->emailText);
    }

    private function queryParticipants()
    {
        $qb = $this->participants
            ->createQueryBuilder('p')
            ->innerJoin('p.attendances', 'attendances')
            ->innerJoin('attendances.offer', 'offer')
            ->leftJoin('offer.edition', 'edition')
        ;

        if (!$this->isGranted('ROLE_ADMIN')) {
            $user = $this->getUser();
            $qb->innerJoin('offer.hosts', 'hosts')->andWhere('hosts IN (:hosts)')->setParameter('hosts', $user instanceof User ? $user->getHosts() : []);
        }

        if ($this->selectedOffers) {
            $qb->andWhere('offer IN (:offers)')->setParameter('offers', $this->selectedOffers);
        }

        if ($this->selectedEditions) {
            $qb->andWhere('edition IN (:editions)')->setParameter('editions', $this->selectedEditions);
        }

        if ($this->attendanceStatus) {
            $qb->andWhere('attendances.status IN (:status)')->setParameter('status', $this->attendanceStatus);
        }

        return $qb->getQuery()->getResult();
    }
}
