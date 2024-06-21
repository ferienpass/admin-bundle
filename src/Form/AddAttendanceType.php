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

namespace Ferienpass\AdminBundle\Form;

use Ferienpass\AdminBundle\Dto\AddAttendanceDto;
use Ferienpass\CoreBundle\Entity\Attendance;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

class AddAttendanceType extends AbstractType
{
    public function __construct(#[Autowire(param: 'ferienpass.model.participant.class')] private readonly string $participantEntityClass)
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefined('add_participant');
        $resolver->setDefault('add_participant', false);
        $resolver->setDefined('add_offer');
        $resolver->setDefault('add_offer', false);
        $resolver->setDefined('new_participant');
        $resolver->setDefault('new_participant', false);

        $resolver->setDefaults([
            'data_class' => AddAttendanceDto::class,
            'label_format' => 'attendances.label.%name%',
            'translation_domain' => 'admin',
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['add_offer']) {
            $builder->add('offer', AddAttendanceOfferType::class);
        }

        if ($options['add_participant'] && !$options['new_participant']) {
            $builder->add('participant', AddAttendanceParticipantType::class);
        }

        if ($options['new_participant']) {
            $builder->add('participant', EditParticipantType::class, [
                'label' => false,
                'show_submit' => false,
                'error_bubbling' => true,
            ]);
        }

        $builder
            ->add('status', ChoiceType::class, [
                'choices' => [Attendance::STATUS_CONFIRMED, Attendance::STATUS_WAITLISTED, Attendance::STATUS_WAITING],
                'choice_label' => fn ($choice): TranslatableMessage => new TranslatableMessage('status.'.$choice),
            ])
            ->add('notify', CheckboxType::class, [
                'required' => false,
                'help' => 'attendances.help.notify',
            ])
            ->add('submit', SubmitType::class)
        ;
    }
}
