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

namespace Ferienpass\AdminBundle\Form\Filter;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Service\Attribute\SubscribedService;
use Symfony\Contracts\Service\ServiceMethodsSubscriberTrait;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

abstract class AbstractFilter extends AbstractType implements ServiceSubscriberInterface
{
    use ServiceMethodsSubscriberTrait;

    protected array $filterTypes = [];

    public function getSearchable(): array
    {
        return array_keys($this->getSorting());
    }

    public function applySortingFor(string $field, QueryBuilder $qb): void
    {
        $callable = $this->getSorting()[$field] ?? null;
        if (!\is_callable($callable)) {
            return;
        }

        $callable($qb);
    }

    public function getFilterable(): array
    {
        return array_keys(static::getFilters());
    }

    public function getSortable(): array
    {
        return array_keys($this->getSorting());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'admin',
            'required' => false,
            'prepopulate' => false,
            'attr' => ['autocomplete' => 'off'],
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach ($this->getFilters() as $filterName => $filterType) {
            if ($builder->has($filterName)) {
                continue;
            }

            $builder->add($filterName, $filterType::class);
        }

        $builder
            ->add('submit', SubmitType::class, [
                'label' => 'Filtern',
            ])
        ;

        if ($options['prepopulate']) {
            return;
        }

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (PreSubmitEvent $event): void {
            // TODO: Currently considers "0" (boolean false) as empty
            $this->requestStack()->getSession()->set('ferienpass_admin.filter.'.static::class, array_filter($event->getData()));
        });
    }

    public function apply(QueryBuilder $qb, FormInterface $form): void
    {
        foreach ($this->getFilters() as $k => $filter) {
            if (!is_a($filter, AbstractFilterType::class, true)) {
                continue;
            }

            $filter->apply($qb, $form->get($k));
        }
    }

    /**
     * @return AbstractFilterType[]
     */
    protected function getFilters(): array
    {
        return $this->filterTypes;
    }

    abstract protected function getSorting(): array;

    #[SubscribedService]
    private function requestStack(): RequestStack
    {
        return $this->container->get(__METHOD__);
    }
}
