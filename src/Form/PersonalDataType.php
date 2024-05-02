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

use Ferienpass\CoreBundle\Entity\User;
use Misd\PhoneNumberBundle\Validator\Constraints\PhoneNumber;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class PersonalDataType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'required' => false,
            'label_format' => 'user.label.%name%',
            'translation_domain' => 'admin',
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstname', options: [
                'constraints' => [
                    new NotBlank(),
                ],
                'width' => '1/2',
                'fieldset_group' => 'personal',
            ])
            ->add('lastname', options: [
                'constraints' => [
                    new NotBlank(),
                ],
                'width' => '1/2',
                'fieldset_group' => 'personal',
            ])
            ->add('email', EmailType::class, [
                'attr' => [
                    'placeholder' => 'email@beispiel.de',
                ],
                'constraints' => [
                    new NotBlank(),
                    new Email(),
                ],
                'width' => '2/3',
                'fieldset_group' => 'personal',
            ])
            ->add('phone', options: [
                'constraints' => [
                    new PhoneNumber(defaultRegion: ' DE'),
                ],
                'width' => '1/2',
                'fieldset_group' => 'personal',
            ])
            ->add('mobile', TextType::class, [
                'constraints' => [
                    new PhoneNumber(type: PhoneNumber::MOBILE, defaultRegion: 'DE'),
                ],
                'width' => '1/2',
                'fieldset_group' => 'personal',
            ])
            ->add('publicFields', ChoiceType::class, [
                'choices' => [
                    'email',
                    'phone',
                    'mobile',
                ],
                'choice_label' => function (string $choice): TranslatableMessage {
                    return new TranslatableMessage('user.label.'.$choice, domain: 'admin');
                },
                'expanded' => true,
                'multiple' => true,
                'fieldset_group' => 'public',
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Daten speichern',
            ])
        ;
    }
}
