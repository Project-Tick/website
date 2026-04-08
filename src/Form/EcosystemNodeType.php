<?php

namespace App\Form;

use App\Entity\EcosystemNode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EcosystemNodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['attr' => ['class' => 'form-control']])
            ->add('shortcut', TextType::class, ['attr' => ['class' => 'form-control', 'placeholder' => 'e.g. CORE']])
            ->add('description', TextType::class, ['attr' => ['class' => 'form-control']])
            ->add('positionX', IntegerType::class, [
                'attr' => ['class' => 'form-control', 'min' => 0, 'max' => 100],
                'help' => 'Horizontal position (%)'
            ])
            ->add('positionY', IntegerType::class, [
                'attr' => ['class' => 'form-control', 'min' => 0, 'max' => 100],
                'help' => 'Vertical position (%)'
            ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Central (Red)' => 'central',
                    'Secondary (Black)' => 'secondary'
                ],
                'attr' => ['class' => 'form-control']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EcosystemNode::class,
        ]);
    }
}
