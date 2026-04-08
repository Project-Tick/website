<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Symfony\Component\HttpFoundation\RequestStack;

class AbsoluteUrlExtension extends AbstractExtension
{
    public function __construct(private RequestStack $requestStack)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('html_to_absolute_urls', [$this, 'convertToAbsoluteUrls'], ['is_safe' => ['html']]),
        ];
    }

    public function convertToAbsoluteUrls(string $content): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return $content;
        }

        $scheme = $request->getScheme();
        $httpHost = $request->getHttpHost();
        $baseUrl = $scheme . '://' . $httpHost;

        // Replace href="/..." with href="baseUrl/..."
        $content = preg_replace_callback('/(href|src)=["\']\/([^"\']*)["\']/', function ($matches) use ($baseUrl) {
            $attribute = $matches[1];
            $path = $matches[2];
            return sprintf('%s="%s/%s"', $attribute, $baseUrl, $path);
        }, $content);

        return $content;
    }
}
