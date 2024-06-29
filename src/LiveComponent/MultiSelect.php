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

class MultiSelect
{
    public function __construct(private readonly array $actions, private readonly string $handler, private readonly array $preferred = [])
    {
    }

    public function getActions(): array
    {
        return $this->actions;
    }

    public function getHandler(): string
    {
        return $this->handler;
    }

    public function getPreferred(): array
    {
        return $this->preferred;
    }
}
