<?php

namespace App\Form;

use App\Entity\LicenseCategory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LicenseCategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', \Symfony\Component\Form\Extension\Core\Type\TextType::class)
            ->add('description', \Symfony\Component\Form\Extension\Core\Type\TextareaType::class, [
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('displayOrder', \Symfony\Component\Form\Extension\Core\Type\IntegerType::class, [
                'label' => 'Order',
                'attr' => ['min' => 0],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LicenseCategory::class,
        ]);
    }
}
