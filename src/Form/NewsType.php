<?php

/*

SPDX-License-Identifier: MIT
SPDX-FileCopyrightText: 2026 Project Tick
SPDX-FileContributor: Project Tick

Copyright (c) 2026 Project Tick

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
of the Software, and to permit persons to whom the Software is furnished to do
so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

*/

namespace App\Form;

use App\Entity\News;
use App\Entity\NewsCategory;
use App\Entity\Product;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NewsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['attr' => ['class' => 'form-control']])
            ->add('slug', TextType::class, ['attr' => ['class' => 'form-control']])
            ->add('date', DateTimeType::class, [
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('content', TextareaType::class, [
                'attr' => ['rows' => 15, 'class' => 'form-control'],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'attr' => ['rows' => 3, 'class' => 'form-control'],
            ])
            ->add('product', EntityType::class, [
                'class' => Product::class,
                'choice_label' => 'title',
                'choice_attr' => function(Product $product) {
                    return ['data-sparkle' => $product->isSparkleEnabled() ? '1' : '0'];
                },
                'placeholder' => 'Root (General News)',
                'required' => false,
                'label' => 'Target Product',
                'attr' => [
                    'class' => 'form-control product-selector',
                    'onchange' => 'toggleSparkleFields(this)'
                ],
                'help' => 'Select a product to enable specific Sparkle release fields if available.'
            ])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Draft' => News::STATUS_DRAFT,
                    'Published' => News::STATUS_PUBLISHED,
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('metadata', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'rows' => 8, 
                    'class' => 'form-control font-monospace metadata-editor',
                    'placeholder' => 'Enter custom JSON here...'
                ],
                'help' => 'This JSON will be merged with the Sparkle release fields below.',
            ])
            // Sparkle-only fields (Not mapped to entity properties directly, but to the metadata array)
            ->add('release_version', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Sparkle: Release Version',
                'attr' => ['class' => 'form-control sparkle-field', 'data-key' => 'release_version'],
                'help' => 'Canonical semver, e.g. 7.0.0 or 0.0.4-2'
            ])
            ->add('release_tag', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Release Tag',
                'attr' => ['class' => 'form-control sparkle-field', 'data-key' => 'release_tag'],
                'help' => 'Git/GitHub release tag, e.g. v202604102316. Defaults to release_version if empty.'
            ])
            ->add('minimum_macos_version', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Sparkle: Min macOS Version',
                'attr' => ['class' => 'form-control sparkle-field', 'data-key' => 'minimum_macos_version'],
                'help' => 'e.g. 12.0.0'
            ])
            ->add('macos_file_extension', ChoiceType::class, [
                'mapped' => false,
                'required' => false,
                'choices' => ['zip' => 'zip', 'dmg' => 'dmg', 'pkg' => 'pkg'],
                'label' => 'Sparkle: File Extension',
                'attr' => ['class' => 'form-control sparkle-field', 'data-key' => 'macos_file_extension'],
            ])
            ->add('macos_signature', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Sparkle: macOS Signature (EdDSA)',
                'attr' => ['class' => 'form-control sparkle-field', 'data-key' => 'macos_signature', 'rows' => 2],
            ])
        ;

        $builder->get('metadata')
            ->addModelTransformer(new CallbackTransformer(
                function ($metadataAsArray) {
                    return json_encode($metadataAsArray, JSON_PRETTY_PRINT);
                },
                function ($metadataAsString) {
                    if (!$metadataAsString) return [];
                    return json_decode($metadataAsString, true) ?: [];
                }
            ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => News::class,
        ]);
    }
}
