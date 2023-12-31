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

namespace Ferienpass\AdminBundle\Form\CompoundType;

use Ferienpass\CoreBundle\Entity\EditionTask;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

class EditionTaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('periodBegin', DateTimeType::class, [
                'date_widget' => 'single_text',
                'minutes' => [0, 15, 30, 45],
            ])
            ->add('periodEnd', DateTimeType::class, [
                'date_widget' => 'single_text',
                'minutes' => [0, 15, 30, 45],
            ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'holiday',
                    'host_editing_stage',
                    'application_system',
                    'allocation',
                    'pay_days',
                    'publish_lists',
                    'show_offers',
                    'custom',
                ],
                'choice_label' => function ($choice): TranslatableMessage|string {
                    return new TranslatableMessage('EditionTask.type_options.'.$choice, [], 'contao_EditionTask');
                },
                'width' => '1/2',
            ])
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $form = $event->getForm();
            /** @var EditionTask $data */
            $data = $event->getData();

            if ('custom' === $data->getType()) {
                $form->add('title');
                $form->add('description');
            }

            if ('application_system' === $data->getType()) {
                $form->add('application_system', ChoiceType::class, [
                    'choices' => [
                        'lot',
                        'firstcome',
                    ],
                    'choice_label' => function ($choice): TranslatableMessage|string {
                        return new TranslatableMessage('MSC.application_system.'.$choice, [], 'contao_default');
                    },
                ]);
            }

            if ($data->isAnApplicationSystem() && 'lot' === $data->getApplicationSystem()) {
                $form->add('max_applications', IntegerType::class, ['help' => 'editions.help.max_applications']);
                $form->add('skip_max_applications', CheckboxType::class, ['help' => 'editions.help.skip_max_applications']);
                $form->add('hide_status', CheckboxType::class, ['help' => 'editions.help.hide_status']);
                $form->add('allow_anonymous', CheckboxType::class, ['help' => 'editions.help.allow_anonymous']);
            }

            if ($data->isAnApplicationSystem() && 'firstcome' === $data->getApplicationSystem()) {
                $form->add('max_applications_day', IntegerType::class, ['help' => 'editions.help.max_applications_per_day']);
                $form->add('allow_anonymous', CheckboxType::class, ['help' => 'editions.help.allow_anonymous']);
            }

            if ($data->isAnApplicationSystem() && $data->isAllowAnonymous()) {
                $form->add('allowAnonymousFee', MoneyType::class, ['help' => 'editions.help.allow_anonymous_fee']);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EditionTask::class,
            // We rely on the fact that the parent form is a DatesType
            // and its parent form is a OfferType that is linked to an Offer entity.
            // 'empty_data' => fn (FormInterface $form) => new OfferDate($form->getParent()->getParent()->getData()->offerEntity()),
        ]);
    }
}
