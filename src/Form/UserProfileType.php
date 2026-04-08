<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\File;

class UserProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
             ->add('username', TextType::class, [
                'required' => false,
                'label' => 'Username',
                'help' => 'This will be used for your public profile URL.',
                'attr' => ['class' => 'form-control']
            ])
            ->add('avatar', FileType::class, [
                'label' => 'Profile Picture',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new File([
                        'maxSize' => '2048k',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image (JPEG, PNG, WEBP)',
                    ])
                ],
            ])
            ->add('biography', TextareaType::class, [
                'required' => false,
                'label' => 'Biography',
                'attr' => ['rows' => 4, 'class' => 'form-control'],
                'help' => 'Tell us a little about yourself.'
            ])
            ->add('country', CountryType::class, [
                'required' => false,
                'label' => 'Country',
                'attr' => ['class' => 'form-control'],
                'placeholder' => 'Select your country'
            ])
            ->add('timezone', TimezoneType::class, [
                'required' => false,
                'label' => 'Timezone',
                'attr' => ['class' => 'form-control'],
                'placeholder' => 'Select your timezone'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
