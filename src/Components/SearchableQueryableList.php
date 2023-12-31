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
use Ferienpass\CoreBundle\Pagination\Paginator;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(name: 'SearchableQueryableList', template: '@FerienpassAdmin/components/SearchableQueryableList.html.twig')]
class SearchableQueryableList
{
    use DefaultActionTrait;

    #[LiveProp]
    public array $config;

    #[LiveProp]
    public array $searchable;

    #[LiveProp(writable: true)]
    public string $query = '';

    #[LiveProp]
    public QueryBuilder $qb;

    #[LiveProp]
    public ?string $originalRoute = null;
    #[LiveProp]
    public ?array $originalQuery = null;

    public function getPagination(): Paginator
    {
        $this->addQueryBuilderSearch();

        if ('' !== $this->query) {
            unset($this->originalQuery['page']);
        }

        return (new Paginator($this->qb, 50))->paginate((int) ($this->originalQuery['page'] ?? 1));
    }

    private function addQueryBuilderSearch(): void
    {
        $where = $this->qb->expr()->andX();
        foreach (array_filter(StringUtil::trimsplit(' ', $this->query)) as $j => $token) {
            $or = $this->qb->expr()->orX();

            foreach ($this->searchable as $i => $field) {
                $or->add("i.$field LIKE :query_$i$j");
                $this->qb->setParameter("query_$i$j", "%{$token}%");
            }

            $where->add($or);
        }

        if ($where->count()) {
            $this->qb->andWhere($where);
        }
    }
}
