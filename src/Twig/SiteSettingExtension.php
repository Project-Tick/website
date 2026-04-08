<?php

namespace App\Twig;

use App\Repository\SiteSettingRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SiteSettingExtension extends AbstractExtension
{
    private SiteSettingRepository $siteSettingRepository;

    public function __construct(SiteSettingRepository $siteSettingRepository)
    {
        $this->siteSettingRepository = $siteSettingRepository;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('site_setting', [$this, 'getSiteSetting']),
        ];
    }

    public function getSiteSetting(string $key, string $default = ''): string
    {
        return $this->siteSettingRepository->getValue($key, $default);
    }
}
