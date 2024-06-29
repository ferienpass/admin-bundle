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

namespace Ferienpass\AdminBundle\LiveComponent;

use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\UX\LiveComponent\Hydration\HydrationExtensionInterface;

class QueryBuilderHydrationExtension implements HydrationExtensionInterface
{
    public function __construct(private readonly ManagerRegistry $doctrine)
    {
    }

    public function supports(string $className): bool
    {
        return is_a($className, QueryBuilder::class, true);
    }

    public function hydrate(mixed $value, string $className): ?object
    {
        $qb = new QueryBuilder($this->doctrine->getManager());

        $value = unserialize($value);
        [$dql, $parameters] = $value;

        foreach ($dql as $k => $v) {
            $qb->add($k, $v);
        }
        $qb->setParameters($parameters);

        return $qb;
    }

    public function dehydrate(object $object): mixed
    {
        /** @var QueryBuilder $object */
        $dql = [];

        foreach ($object->getDQLParts() as $part => $elements) {
            if (\is_array($elements)) {
                foreach ($elements as $idx => $element) {
                    if (\is_object($element)) {
                        $dql[$part][$idx] = $element;
                    } else {
                        foreach ($element as $k => $v) {
                            if (\is_object($v)) {
                                $dql[$part][$idx][$k] = $v;
                            }
                        }
                    }
                }
            } elseif (\is_object($elements)) {
                $dql[$part] = $elements;
            }
        }

        return serialize([$dql, $object->getParameters()]);
    }
}
