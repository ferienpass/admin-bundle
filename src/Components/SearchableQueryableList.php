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
use Ferienpass\AdminBundle\Form\Filter\AbstractFilter;
use Ferienpass\AdminBundle\LiveComponent\MultiSelect;
use Ferienpass\AdminBundle\LiveComponent\MultiSelectHandlerInterface;
use Ferienpass\CoreBundle\Pagination\Paginator;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsLiveComponent(route: 'live_component_admin')]
class SearchableQueryableList extends AbstractController
{
    use ComponentToolsTrait;
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    #[LiveProp]
    public array $config;

    #[LiveProp]
    public array $searchable = [];

    #[LiveProp(writable: true)]
    public string $query = '';

    #[LiveProp]
    public QueryBuilder $qb;

    #[LiveProp(hydrateWith: 'hydrateMs', dehydrateWith: 'dehydrateMs')]
    public MultiSelect|null $ms = null;

    #[LiveProp(writable: true)]
    public array $selected = [];

    #[LiveProp(writable: true)]
    public bool $selectedAll = false;

    #[LiveProp]
    public ?string $routeName = null;
    #[LiveProp]
    public ?array $routeParameters = null;

    #[LiveProp(writable: true)]
    public string $sorting = '';

    #[LiveProp]
    public ?string $filterType = null;

    public function __construct(private readonly FormFactoryInterface $formFactory, #[TaggedLocator('ferienpass_admin.filter')] private readonly ServiceLocator $filters, private readonly RequestStack $requestStack, public readonly EventDispatcherInterface $dispatcher, private ContainerInterface $locator, #[TaggedLocator('ferienpass_admin.ms_handler')] private ServiceLocator $multiSelectHandlers)
    {
    }

    public function getPagination(): Paginator
    {
        $this->addQueryBuilderSearch();
        $this->addQueryBuilderSorting();

        if (null !== $filter = $this->getFilter()) {
            $filter->apply($this->qb, $this->getForm());
        }

        if ('' !== $this->query) {
            unset($this->routeParameters['page']);
        }

        return (new Paginator($this->qb, 50))->paginate((int) ($this->routeParameters['page'] ?? 1));
    }

    #[LiveListener('admin_list:changed')]
    public function changed()
    {
        // no need to do anything here: the component will re-render
    }

    public function getSortingFields(): array
    {
        return $this->getFilter()?->getSearchable() ?? [];
    }

    public function getFilters(): array
    {
        return $this->getFilter()?->getFilterable() ?? [];
    }

    #[LiveAction]
    public function filter(): RedirectResponse
    {
        if (null === $this->getFilter()) {
            return $this->redirectToRoute($this->routeName, $this->routeParameters);
        }

        $this->submitForm();

        $filterData = [];

        foreach (array_keys((array) $this->getForm()->getViewData()) as $attr) {
            if (!$this->getForm()->has($attr)) {
                continue;
            }

            $f = $this->getForm()->get($attr);
            $v = $f->getViewData();
            if ($f->isEmpty() && !($this->routeParameters[$attr] ?? null)) {
                continue;
            }

            $filterData[$attr] = $v;
        }

        $this->routeParameters = array_merge($this->routeParameters, $filterData);
        unset($this->routeParameters['page']);

        return $this->redirectToRoute($this->routeName, array_filter($this->routeParameters));
    }

    #[LiveAction]
    public function unsetFilter(#[LiveArg] string $filterName): Response
    {
        unset($this->routeParameters[$filterName]);

        $filter = $this->getFilter();
        $this->requestStack->getSession()->set('ferienpass_admin.filter.'.$filter::class, array_filter($this->requestStack->getSession()->get('ferienpass_admin.filter.'.$filter::class, []), fn (string $k) => $k !== $filterName, \ARRAY_FILTER_USE_KEY));

        return $this->redirectToRoute($this->routeName, array_filter($this->routeParameters));
    }

    #[LiveAction]
    public function paginate(#[LiveArg] int $page): void
    {
        $this->routeParameters['page'] = $page;
    }

    #[LiveAction]
    public function selectAll(): void
    {
        $allItems = iterator_to_array($this->getPagination()->getResults());
        if (\count($this->selected) < \count($allItems)) {
            if (0 === \count($this->selected) && 0 !== \count($this->ms->getPreferred())) {
                $this->selected = $this->ms->getPreferred();
                $this->selectedAll = false;
            } else {
                $this->selected = array_map(fn ($item) => $item->getId(), $allItems);
                $this->selectedAll = true;
            }
        } else {
            $this->selected = [];
            $this->selectedAll = false;
        }
    }

    #[LiveAction]
    public function submitMultiSelect(#[LiveArg('actionName')] string $action, Request $request): Response
    {
        /** @var MultiSelectHandlerInterface $controller */
        $controller = $this->multiSelectHandlers->get($this->ms->getHandler());

        return $controller->handleMultiSelect($action, $this->selected, $request);
    }

    #[ExposeInTemplate]
    public function msButtons(): array
    {
        if (null === $this->ms) {
            return [];
        }

        return $this->ms->getActions();
    }

    #[ExposeInTemplate]
    public function entityClass(): string
    {
        return explode(' ', (string) $this->qb->getDQLPart('from')[0], 2)[0];
    }

    public function dehydrateMs(MultiSelect|null $ms): array|null
    {
        if (null === $ms) {
            return null;
        }

        return [
            'actions' => $ms->getActions(),
            'handler' => $ms->getHandler(),
            'preferred' => $ms->getPreferred(),
        ];
    }

    public function hydrateMs(array|null $data): MultiSelect|null
    {
        if (null === $data) {
            return null;
        }

        return new MultiSelect($data['actions'], $data['handler'], $data['preferred']);
    }

    protected function instantiateForm(): FormInterface
    {
        if (null === $filter = $this->getFilter()) {
            return $this->formFactory->create();
        }

        $filterDataFromUrl = array_filter($this->routeParameters, fn (string $key) => \in_array($key, $this->getFilters(), true), \ARRAY_FILTER_USE_KEY);

        $session = $this->requestStack->getSession();
        $filterDataFromSession = $session->get('ferienpass_admin.filter.'.$filter::class, []);

        $filterData = array_merge($filterDataFromSession, $filterDataFromUrl);

        $filterForm = $this->formFactory->create($filter::class, options: ['prepopulate' => true]);
        $filterForm->submit($filterData);

        return $this->formFactory->create($filter::class, $filterForm->getData());
    }

    private function addQueryBuilderSearch(): void
    {
        $where = $this->qb->expr()->andX();
        foreach (array_filter(StringUtil::trimsplit(' ', $this->query)) as $j => $token) {
            $or = $this->qb->expr()->orX();

            foreach ($this->searchable as $i => $field) {
                $qfield = str_contains($field, '.') ? $field : "i.$field";
                $or->add("$qfield LIKE :query_$i$j");
                $this->qb->setParameter("query_$i$j", "%{$token}%");
            }

            $where->add($or);
        }

        if ($where->count()) {
            $this->qb->andWhere($where);
        }
    }

    private function addQueryBuilderSorting(): void
    {
        if ('' === $this->sorting) {
            $this->sorting = $this->getFilter()?->getSortable()[0] ?? '';
        }

        $this->getFilter()?->applySortingFor($this->sorting, $this->qb);
    }

    private function getFilter(): ?AbstractFilter
    {
        if (null === $this->filterType) {
            return null;
        }

        if (!(($filter = $this->filters->get($this->filterType)) instanceof AbstractFilter)) {
            return null;
        }

        return $filter;
    }
}
