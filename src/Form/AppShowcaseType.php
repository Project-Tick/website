<?php

namespace App\Form;

use App\Entity\AppShowcase;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AppShowcaseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['attr' => ['class' => 'form-control']])
            ->add('developer', TextType::class, ['attr' => ['class' => 'form-control']])
            ->add('websiteUrl', UrlType::class, ['attr' => ['class' => 'form-control']])
            ->add('imageUrl', UrlType::class, ['attr' => ['class' => 'form-control']])
            ->add('shortDescription', TextType::class, ['attr' => ['class' => 'form-control']])
            ->add('isFeatured', CheckboxType::class, ['required' => false, 'attr' => ['class' => 'form-check-input']])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AppShowcase::class,
        ]);
    }
}
