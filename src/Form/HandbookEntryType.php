<?php

namespace App\Form;

use App\Entity\HandbookEntry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HandbookEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', \Symfony\Component\Form\Extension\Core\Type\TextType::class, ['attr' => ['class' => 'form-control']])
            ->add('slug', \Symfony\Component\Form\Extension\Core\Type\TextType::class, ['attr' => ['class' => 'form-control']])
            ->add('content', \Symfony\Component\Form\Extension\Core\Type\TextareaType::class, [
                'attr' => ['rows' => 20, 'class' => 'form-control']
            ])
            ->add('category', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, [
                'choices' => [
                    'Documentation' => 'DOCUMENTATION',
                    'Developer' => 'Developer',
                    'Wiki' => 'WIKI',
                    'Other' => 'OTHER'
                ],
                'attr' => ['class' => 'form-control']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => HandbookEntry::class,
        ]);
    }
}
