<?php

namespace App\Form;

use App\Entity\LicenseComparison;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LicenseComparisonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('feature', TextType::class, [
                'label' => 'Comparison Feature',
                'help' => 'e.g., "AI Training Protection"',
                'attr' => ['class' => 'form-control']
            ])
            ->add('mitApache', TextType::class, [
                'label' => 'MIT / Apache 2.0 Value',
                'help' => 'e.g., "❌ No"',
                'attr' => ['class' => 'form-control']
            ])
            ->add('gnuGpl', TextType::class, [
                'label' => 'GNU GPL v2 / v3 Value',
                'help' => 'e.g., "⚠️ Partial"',
                'attr' => ['class' => 'form-control']
            ])
            ->add('projectTick', TextType::class, [
                'label' => 'Project Tick Value',
                'help' => 'e.g., "✅ Explicit Rules"',
                'attr' => ['class' => 'form-control']
            ])
            ->add('displayOrder', IntegerType::class, [
                'label' => 'Sort Order',
                'attr' => ['class' => 'form-control']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LicenseComparison::class,
        ]);
    }
}
