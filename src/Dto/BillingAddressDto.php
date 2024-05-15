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

namespace Ferienpass\AdminBundle\Dto;

use Doctrine\Common\Collections\Collection;
use Ferienpass\CoreBundle\Entity\Payment;
use Ferienpass\CoreBundle\Entity\PaymentItem;
use Ferienpass\CoreBundle\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

class BillingAddressDto
{
    #[Assert\NotBlank]
    public ?string $address = null;

    #[Assert\Email]
    public ?string $email = null;

    /** @var Collection<PaymentItem> */
    public Collection $items;

    public static function fromUser(User $user): self
    {
        $self = new self();

        $self->address = <<<EOF
{$user->getName()}
{$user->getStreet()}
{$user->getPostal()} {$user->getCity()}
EOF;

        $self->email = $user->getEmail();
        $self->address = trim($self->address);

        return $self;
    }

    public static function fromPayment(Payment $payment): self
    {
        $participant = null;

        /** @var PaymentItem $item */
        if (false !== $item = $payment->getItems()->first()) {
            $participant = $item->getAttendance()->getParticipant();
        }

        if (null === $participant?->getUser()) {
            $self = new self();
        } else {
            $self = self::fromUser($participant->getUser());
        }

        $self->items = $payment->getItems();

        if ($participant?->getEmail()) {
            $self->email = $participant->getEmail();
        }

        return $self;
    }

    public function toPayment(Payment $payment): Payment
    {
        $payment->setBillingAddress($this->address);
        $payment->setBillingEmail($this->email);

        foreach ($this->items as $item) {
            $payment->addItem(new PaymentItem($item->getAttendance(), $item->getAmount()));
        }

        return $payment;
    }
}
