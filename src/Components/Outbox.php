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

use Contao\StringUtil;
use Doctrine\ORM\QueryBuilder;
use Ferienpass\CoreBundle\Entity\SentMessage;
use Ferienpass\CoreBundle\Pagination\Paginator;
use Ferienpass\CoreBundle\Repository\SentEmailRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ValidatableComponentTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsLiveComponent(route: 'live_component_admin')]
class Outbox extends AbstractController
{
    use ComponentToolsTrait;
    use DefaultActionTrait;
    use ValidatableComponentTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    #[LiveProp(url: true)]
    public ?SentMessage $message = null;

    private QueryBuilder $qb;

    private int $page = 1;

    public function __construct(private readonly SentEmailRepository $repository)
    {
        // TODO full outer join SentSms (ALT: tabs in the interface)
        $this->qb = $this->repository->createQueryBuilder('email');
    }

    #[ExposeInTemplate]
    public function getPagination(): Paginator
    {
        $this->qb->orderBy('email.createdAt', 'DESC');

        $this->addQueryBuilderSearch();

        return (new Paginator($this->qb, 25 * $this->page))->paginate();
    }

    #[LiveListener('open')]
    public function openMessage(#[LiveArg] SentMessage $message): void
    {
        $this->message = $message;
    }

    #[LiveListener('loadMore')]
    public function loadMore(): void
    {
        ++$this->page;
    }

    private function addQueryBuilderSearch(): void
    {
        $where = $this->qb->expr()->andX();
        foreach (array_filter(StringUtil::trimsplit(' ', $this->query)) as $j => $token) {
            $where->add("email.emailData LIKE :query_$j");
            $this->qb->setParameter("query_$j", "%{$token}%");
        }

        if ($where->count()) {
            $this->qb->andWhere($where);
        }
    }
}
