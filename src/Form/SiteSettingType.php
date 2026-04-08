<?php

namespace App\Form;

use App\Entity\SiteSetting;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SiteSettingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('category', ChoiceType::class, [
                'choices' => [
                    'General' => 'General',
                    'Home Page' => 'Home Page',
                    'Handbook' => 'Handbook',
                    'News' => 'News',
                    'Product Pages' => 'Product',
                    'Licenses' => 'Licenses',
                    'About' => 'About',
                    'Footer' => 'Footer',
                    'CLA & Legal' => 'CLA',
                    'Dashboard' => 'Dashboard',
                ],
                'label' => 'Category',
            ])
            ->add('settingKey', ChoiceType::class, [
                'choices' => [
                    'General' => [
                        'Site Title' => 'site_title',
                        'Brand Name' => 'brand_name',
                    ],
                    'Navigation' => [
                        'News Label' => 'nav_news_label',
                        'Handbook Label' => 'nav_handbook_label',
                        'Licenses Label' => 'nav_licenses_label',
                        'Products Dropdown' => 'nav_products_label',
                        'Login Button' => 'nav_login_btn',
                        'Logout Button' => 'nav_logout_btn',
                        'Panel Badge' => 'nav_panel_badge',
                    ],
                    'Cookie Consent' => [
                        'Consent Text' => 'cookie_text',
                        'Accept Button' => 'cookie_accept_btn',
                        'Reject Button' => 'cookie_reject_btn',
                    ],
                    'Footer' => [
                        'Footer Text' => 'footer_text',
                        'GitHub URL' => 'social_github',
                        'GitHub Label' => 'footer_social_github_label',
                        'Discord URL' => 'social_discord',
                        'Discord Label' => 'footer_social_discord_label',
                        'Twitter URL' => 'social_twitter',
                        'Twitter Label' => 'footer_social_twitter_label',
                        'TOS Label' => 'footer_legal_tos_label',
                        'Privacy Label' => 'footer_legal_privacy_label',
                    ],
                    'CLA & Legal' => [
                        'CLA Page Title' => 'cla_title',
                        'CLA Page Header' => 'cla_description',
                        'CLA Agreement Text' => 'cla_agreement_text',
                        'CLA Page Slug' => 'cla_active_slug',
                        'Terms of Service URL' => 'legal_tos_url',
                        'Privacy Policy URL' => 'legal_privacy_url',
                    ],
                    'Licenses Page' => [
                        'Intro Text' => 'licenses_intro_text',
                        'Alert Box Content' => 'licenses_alert_box_content',
                        'Comparison Col 1' => 'licenses_comp_col1',
                        'Comparison Col 2' => 'licenses_comp_col2',
                        'Comparison Col 3' => 'licenses_comp_col3',
                        'Raw TXT Label' => 'licenses_raw_txt_btn',
                        'Category Label' => 'licenses_category_label',
                        'SPDX Label' => 'licenses_spdx_label',
                        'Usage Title' => 'licenses_usage_title',
                        'Legal Text Title' => 'licenses_legal_text_title',
                        'Back to Licenses' => 'licenses_back_link',
                    ],
                    'Launcher Page' => [
                        'Download Label' => 'launcher_download_btn',
                        'News Label' => 'launcher_news_btn',
                        'About Label' => 'launcher_about_btn',
                        'GitHub Label' => 'launcher_github_btn',
                        'Not Found Title' => 'launcher_not_found_title',
                        'Not Found Body' => 'launcher_not_found_body',
                        'Go Home Label' => 'launcher_go_home_btn',
                        'Feature Card Title' => 'launcher_feature_title',
                    ],
                    'Home Page' => [
                        'Hero Title' => 'home_hero_title',
                        'Hero Description' => 'home_hero_desc',
                        'Hero Button 1' => 'home_hero_btn1',
                        'Hero Button 1 URL' => 'home_hero_url1',
                        'Hero Button 2' => 'home_hero_btn2',
                        'Hero Button 2 URL' => 'home_hero_url2',
                        'Card 1 Title' => 'home_card1_title',
                        'Card 1 Body' => 'home_card1_body',
                        'Card 1 Link' => 'home_card1_btn',
                        'Card 2 Title' => 'home_card2_title',
                        'Card 2 Body' => 'home_card2_body',
                        'Card 2 Link' => 'home_card2_btn',
                    ],
                    'Handbook' => [
                        'Handbook Title' => 'handbook_title',
                        'Handbook Subtitle' => 'handbook_subtitle',
                        'Search Placeholder' => 'handbook_search_placeholder',
                        'Search Clear' => 'handbook_search_clear',
                        'Doc Areas Title' => 'handbook_areas_title',
                        'Search Results Title' => 'handbook_search_results_title',
                        'No Results Text' => 'handbook_no_results',
                        'CTA Title' => 'handbook_cta_title',
                        'CTA Body' => 'handbook_cta_body',
                        'CTA Link' => 'handbook_cta_btn',
                        'Sidebar Header' => 'handbook_side_header',
                        'Back to Index' => 'handbook_back_link',
                        'Last Updated Label' => 'handbook_last_updated',
                        'Edit on GitHub' => 'handbook_edit_github',
                    ],
                    'News Section' => [
                        'Latest Updates Title' => 'news_latest_title',
                        'News List Desc' => 'news_list_desc',
                        'News Empty Text' => 'news_empty_text',
                        'Read More Link' => 'news_read_more',
                        'Back to News' => 'news_back_link',
                        'Published On' => 'news_published_on',
                    ],
                    'About Page' => [
                        'Fallback Title' => 'about_title_fallback',
                        'Fallback Desc 1' => 'about_default_desc_1',
                        'Fallback Desc 2' => 'about_default_desc_2',
                        'Fallback Feature 1' => 'about_feat_1',
                        'Fallback Feature 2' => 'about_feat_2',
                        'Fallback Feature 3' => 'about_feat_3',
                        'Fallback Feature 4' => 'about_feat_4',
                        'Fallback Feature 5' => 'about_feat_5',
                        'Contributors Title' => 'about_contr_title',
                        'Thanks Text' => 'about_contr_thanks',
                        'Empty Contr' => 'about_contr_empty',
                    ],
                    'User Dashboard' => [
                        'Welcome Subtitle' => 'user_dash_subtitle',
                        'Resources Title' => 'user_dash_resources_title',
                        'Resources Body' => 'user_dash_resources_body',
                        'Resources Link' => 'user_dash_resources_btn',
                        'Licenses Title' => 'user_dash_licenses_title',
                        'Licenses Body' => 'user_dash_licenses_body',
                        'Licenses Link' => 'user_dash_licenses_btn',
                    ]
                ],
                'label' => 'Setting Name',
                'help' => 'Select which setting you want to configure.',
            ])
            ->add('description', TextType::class, [
                'label' => 'Description',
                'help' => 'Explain what this setting controls.',
                'required' => false,
            ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Text (Single Line)' => 'text',
                    'HTML / Long Text' => 'html',
                ],
                'label' => 'Content Type',
            ])
            ->add('settingValue', TextareaType::class, [
                'label' => 'Value',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SiteSetting::class,
        ]);
    }
}
