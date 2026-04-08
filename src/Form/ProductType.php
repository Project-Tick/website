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

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Entity\License;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['attr' => ['class' => 'form-control']])
            ->add('slug', TextType::class, [
                'help' => 'URL-friendly name (e.g., projt-launcher)',
                'attr' => ['class' => 'form-control']
            ])
            ->add('summary', TextType::class, [
                'help' => 'Short one-line description.',
                'attr' => ['class' => 'form-control']
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'Homepage Description (Main)',
                'attr' => [
                    'rows' => 15,
                    'class' => 'form-control font-monospace',
                ],
                'help' => 'Content shown on the main product landing page.',
            ])
            ->add('aboutContent', TextareaType::class, [
                'required' => false,
                'label' => 'About Page Content (Dedicated)',
                'attr' => [
                    'rows' => 20,
                    'class' => 'form-control font-monospace',
                ],
                'help' => 'Content shown on the /about page. If left empty, a default fallback will be used.',
            ])
            ->add('version', TextType::class, [
                'required' => false,
                'label' => 'Current Version',
                'attr' => ['class' => 'form-control']
            ])
            ->add('ftpFolderName', TextType::class, [
                'required' => false,
                'label' => 'FTP Folder Name',
                'help' => 'Folder name in the FTP server (e.g., ProjT-Launcher). Default: ProjT-Launcher',
                'attr' => ['class' => 'form-control']
            ])
            ->add('downloadUrl', UrlType::class, [
                'required' => false,
                'label' => 'Download URL',
                'attr' => ['class' => 'form-control']
            ])
            ->add('githubUrl', UrlType::class, [
                'required' => false,
                'label' => 'GitHub Repository URL',
                'attr' => ['class' => 'form-control']
            ])
            ->add('gitlabUrl', UrlType::class, [
                'required' => false,
                'label' => 'GitLab Repository URL',
                'attr' => ['class' => 'form-control']
            ])
            ->add('imageUrl', TextType::class, [
                'required' => false,
                'label' => 'Image Path/URL',
                'help' => 'Path to image (e.g., /img/launcher-main.png) or full URL.',
                'attr' => ['class' => 'form-control']
            ])
            ->add('productType', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, [
                'choices' => [
                    'Software' => 'software',
                    'Service' => 'service',
                    'Library' => 'library',
                    'Policy' => 'policy',
                ],
                'required' => false,
                'label' => 'Product Type',
                'attr' => ['class' => 'form-control']
            ])
            ->add('productStatus', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, [
                'choices' => [
                    'Active' => 'active',
                    'Deprecated' => 'deprecated',
                    'Archived' => 'archived',
                ],
                'required' => false,
                'label' => 'Status',
                'attr' => ['class' => 'form-control']
            ])
            ->add('productVisibility', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, [
                'choices' => [
                    'Public' => 'public',
                    'Internal' => 'internal',
                ],
                'required' => false,
                'label' => 'Visibility',
                'attr' => ['class' => 'form-control']
            ])
            ->add('productOwner', TextType::class, [
                'required' => false,
                'label' => 'Owner (User/Org)',
                'attr' => ['class' => 'form-control']
            ])
            ->add('features', CollectionType::class, [
                'entry_type' => TextType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'required' => false,
                'label' => 'Key Features',
                'entry_options' => ['label' => false, 'attr' => ['class' => 'form-control']],
            ])
            ->add('allowWindows', \Symfony\Component\Form\Extension\Core\Type\CheckboxType::class, [
                'required' => false,
                'label' => 'Enable Windows Download',
            ])
            ->add('useHtmlWindows', \Symfony\Component\Form\Extension\Core\Type\CheckboxType::class, [
                'required' => false,
                'label' => 'Use HTML (instead of Markdown)',
            ])
            ->add('downloadContentWindows', TextareaType::class, [
                'required' => false,
                'label' => 'Windows Download Content',
                'attr' => ['rows' => 10, 'class' => 'form-control font-monospace'],
            ])
            ->add('allowMacos', \Symfony\Component\Form\Extension\Core\Type\CheckboxType::class, [
                'required' => false,
                'label' => 'Enable macOS Download',
            ])
            ->add('useHtmlMacos', \Symfony\Component\Form\Extension\Core\Type\CheckboxType::class, [
                'required' => false,
                'label' => 'Use HTML (instead of Markdown)',
            ])
            ->add('downloadContentMacos', TextareaType::class, [
                'required' => false,
                'label' => 'macOS Download Content',
                'attr' => ['rows' => 10, 'class' => 'form-control font-monospace'],
            ])
            ->add('allowLinux', \Symfony\Component\Form\Extension\Core\Type\CheckboxType::class, [
                'required' => false,
                'label' => 'Enable Linux Download',
            ])
            ->add('useHtmlLinux', \Symfony\Component\Form\Extension\Core\Type\CheckboxType::class, [
                'required' => false,
                'label' => 'Use HTML (instead of Markdown)',
            ])
            ->add('downloadContentLinux', TextareaType::class, [
                'required' => false,
                'label' => 'Linux Download Content',
                'attr' => ['rows' => 10, 'class' => 'form-control font-monospace'],
            ])
            ->add('isSparkleEnabled', \Symfony\Component\Form\Extension\Core\Type\CheckboxType::class, [
                'required' => false,
                'label' => 'Enable Sparkle Appcast Support',
                'help' => 'Check this if the product uses Sparkle framework for macOS updates. This will enable advanced metadata fields in News/Release posts.',
            ])
            ->add('requiresLicense', \Symfony\Component\Form\Extension\Core\Type\CheckboxType::class, [
                'required' => false,
                'label' => 'Requires Valid License to Download',
                'help' => 'If checked, users must have an active license assigned to them to see download links.',
            ])
            ->add('price', TextType::class, [
                'required' => false,
                'label' => 'Product Price (e.g. 29.99)',
                'help' => 'Leave empty or 0 for free/public products.',
                'attr' => ['class' => 'form-control']
            ])
            ->add('license', EntityType::class, [
                'class' => License::class,
                'choice_label' => 'name',
                'required' => false,
                'label' => 'Commercial License (Optional)',
                'placeholder' => '-- Select a Commercial License --',
                'attr' => ['class' => 'form-control']
            ])
            ->add('spdxLicense', EntityType::class, [
                'class' => \App\Entity\SpdxLicense::class,
                'choice_label' => 'name',
                'required' => false,
                'label' => 'Open Source (SPDX) License (Optional)',
                'placeholder' => '-- Select an SPDX License --',
                'attr' => ['class' => 'form-control']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
