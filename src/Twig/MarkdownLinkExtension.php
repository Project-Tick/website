<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class MarkdownLinkExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('convert_markdown_links', [$this, 'convertLinks'], ['is_safe' => ['html']]),
        ];
    }

    public function convertLinks(string $content, string $prefix = '/handbook/'): string
    {
        // 1. Convert [Title](./file.md) to [Title](/handbook/file)
        $content = preg_replace_callback('/\[([^\]]+)\]\(\.\/([^)]+)\.md\)/', function ($matches) use ($prefix) {
            $slug = $matches[2];
            return '[' . $matches[1] . '](' . $prefix . $slug . ')';
        }, $content);

        // 2. Convert [Title](file.md) to [Title](/handbook/file)
        $content = preg_replace_callback('/\[([^\]]+)\]\(([^)\/]+)\.md\)/', function ($matches) use ($prefix) {
            $slug = $matches[2];
            return '[' . $matches[1] . '](' . $prefix . $slug . ')';
        }, $content);

        // 3. Convert /projtlauncher/img/ to /img/
        $content = str_replace('/projtlauncher/img/', '/img/', $content);

        return $content;
    }
}
