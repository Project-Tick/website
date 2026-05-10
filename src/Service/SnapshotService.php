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

namespace App\Service;

/**
 * Scans the FTP directory for per-product release tags and resolves the
 * latest release per (product, channel), producing a single latest.json that
 * groups channels (stable / beta / lts) under each product key.
 *
 * Filesystem layout:
 *   <FTP_ROOT>/<product>/releases/download/<tag>/<files...>
 *
 * Tag format: <product>-<version>[-<suffix>]
 *   stable — <product>-vX.Y.Z              (e.g. meshmc-v7.19.0)
 *   beta   — <product>-YYYYMMDDHHmm-betaN  (e.g. meshmc-202605090000-beta1)
 *   lts    — <product>-YYYYMMDDHHmm-ltsN   (e.g. meshmc-202605090000-lts1)
 *
 * Note: the leading "v" prefix is present only on stable (semver) tags;
 * beta and lts tags use a bare timestamp + suffix.
 *
 * Tags that do not match any of the channel patterns above (including legacy
 * monorepo snapshot tags such as v202604191350, vBETA…, vLTS…) are silently
 * ignored.
 */
class SnapshotService
{
    private const FTP_ROOT = '/var/www/ftp/Project-Tick';
    private const DOWNLOAD_ROOT_URL = 'https://ftp.projecttick.org/Project-Tick';

    public const CHANNELS = ['stable', 'beta', 'lts'];

    /**
     * Anchored regex patterns matching the version+suffix portion of a tag
     * (i.e. everything after "<product>-"). Capture groups expose components
     * used for channel-aware sorting.
     *
     *   stable: v(X).(Y).(Z)
     *   beta:   (YYYYMMDDHHmm)-beta(N)
     *   lts:    (YYYYMMDDHHmm)-lts(N)
     */
    private const CHANNEL_VERSION_PATTERNS = [
        'stable' => '/^v(\d+)\.(\d+)\.(\d+)$/',
        'beta'   => '/^(\d{12})-beta(\d+)$/',
        'lts'    => '/^(\d{12})-lts(\d+)$/',
    ];

    /**
     * Discover all product names that exist on the FTP.
     *
     * A product is any direct subdirectory of FTP_ROOT that contains at least
     * one release tag matching one of the channel patterns under
     * releases/download/.
     *
     * @return string[]
     */
    public function discoverProducts(): array
    {
        if (!is_dir(self::FTP_ROOT)) {
            return [];
        }

        $entries = scandir(self::FTP_ROOT) ?: [];
        $products = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = self::FTP_ROOT . '/' . $entry;
            if (!is_dir($path)) {
                continue;
            }
            if (!is_dir($path . '/releases/download')) {
                continue;
            }
            // Only register the product if it has at least one tag we recognise.
            if (!empty($this->listProductTags($entry))) {
                $products[] = $entry;
            }
        }

        sort($products);
        return $products;
    }

    /**
     * List every recognised release tag for a product, classified by channel.
     *
     * @return array<int, array{tag: string, channel: string, sort: array<int>}>
     */
    private function listProductTags(string $product): array
    {
        $downloadDir = self::FTP_ROOT . '/' . $product . '/releases/download';
        if (!is_dir($downloadDir)) {
            return [];
        }

        $entries = scandir($downloadDir) ?: [];
        $prefix = $product . '-';
        $results = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (!is_dir($downloadDir . '/' . $entry)) {
                continue;
            }
            if (!str_starts_with($entry, $prefix)) {
                // Legacy global tag (v202604…, vBETA…, vLTS…) — ignore.
                continue;
            }

            $versionPart = substr($entry, strlen($prefix));
            $classified = $this->classifyVersionPart($versionPart);
            if ($classified === null) {
                continue;
            }

            $results[] = [
                'tag'     => $entry,
                'channel' => $classified[0],
                'sort'    => $classified[1],
            ];
        }

        return $results;
    }

    /**
     * Classify a tag's version+suffix portion into a channel and produce a
     * numeric sort key.
     *
     * @return array{0: string, 1: array<int>}|null
     */
    private function classifyVersionPart(string $versionPart): ?array
    {
        foreach (self::CHANNEL_VERSION_PATTERNS as $channel => $pattern) {
            if (preg_match($pattern, $versionPart, $m)) {
                array_shift($m);
                $parts = array_map('intval', $m);
                return [$channel, $parts];
            }
        }
        return null;
    }

    /**
     * Find the latest release tag for the given (product, channel) pair, or
     * null if no recognised tag exists.
     */
    public function findLatestSnapshotTag(string $product, string $channel = 'stable'): ?string
    {
        if (!in_array($channel, self::CHANNELS, true)) {
            return null;
        }

        $candidates = array_values(array_filter(
            $this->listProductTags($product),
            static fn (array $row): bool => $row['channel'] === $channel,
        ));

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, function (array $a, array $b): int {
            $pa = $a['sort'];
            $pb = $b['sort'];
            $len = max(count($pa), count($pb));
            for ($i = 0; $i < $len; $i++) {
                $va = $pa[$i] ?? 0;
                $vb = $pb[$i] ?? 0;
                if ($va !== $vb) {
                    return $vb <=> $va;
                }
            }
            return 0;
        });

        return $candidates[0]['tag'];
    }

    /**
     * Extract the human-friendly version string from a tag.
     *
     * stable: "meshmc-v7.19.0"            → "7.19.0"
     * beta:   "meshmc-202605090000-beta1" → "202605090000-beta1"
     * lts:    "meshmc-202605090000-lts1"  → "202605090000-lts1"
     */
    private function versionFromTag(string $product, string $tag, string $channel): string
    {
        $versionPart = substr($tag, strlen($product) + 1); // strip "<product>-"
        if ($channel === 'stable' && str_starts_with($versionPart, 'v')) {
            return substr($versionPart, 1);
        }
        return $versionPart;
    }

    /**
     * Derive a release date for a tag. For beta/lts the embedded YYYYMMDDHHmm
     * timestamp is used; for stable the directory mtime is used as a
     * pragmatic fallback.
     */
    private function releaseDateFor(string $product, string $tag, string $channel): string
    {
        if ($channel !== 'stable') {
            $versionPart = substr($tag, strlen($product) + 1);
            if (preg_match('/^(\d{4})(\d{2})(\d{2})\d{4}-/', $versionPart, $m)) {
                return sprintf('%s-%s-%s', $m[1], $m[2], $m[3]);
            }
        }

        $releaseDir = self::FTP_ROOT . '/' . $product . '/releases/download/' . $tag;
        if (is_dir($releaseDir)) {
            $mtime = @filemtime($releaseDir);
            if ($mtime !== false) {
                return date('Y-m-d', $mtime);
            }
        }
        return date('Y-m-d');
    }

    /**
     * Build the per-channel data structure for the given (product, channel).
     *
     * @return array{
     *     release_tag: string,
     *     version: string,
     *     release_date: string,
     *     download_url: string,
     *     files: array<array{name: string, url: string, size: int}>
     * }|null
     */
    public function buildChannelData(string $product, string $channel): ?array
    {
        $tag = $this->findLatestSnapshotTag($product, $channel);
        if ($tag === null) {
            return null;
        }

        $releaseDir = self::FTP_ROOT . '/' . $product . '/releases/download/' . $tag;
        $downloadUrl = self::DOWNLOAD_ROOT_URL . '/' . $product . '/releases/download/' . $tag . '/';

        return [
            'release_tag'  => $tag,
            'version'      => $this->versionFromTag($product, $tag, $channel),
            'release_date' => $this->releaseDateFor($product, $tag, $channel),
            'download_url' => $downloadUrl,
            'files'        => $this->listReleaseFiles($releaseDir, $product, $tag),
        ];
    }

    /**
     * Build the full latest.json structure grouped by product.
     * Products with no releases in any channel are omitted; within a product,
     * channels with no release are likewise omitted.
     *
     * Shape:
     *   {
     *     "schema_version": 2,
     *     "products": {
     *       "<product>": {
     *         "stable": { ... } | omitted,
     *         "beta":   { ... } | omitted,
     *         "lts":    { ... } | omitted
     *       }
     *     }
     *   }
     */
    public function buildLatestJson(): array
    {
        $result = [
            'schema_version' => 2,
            'products'       => [],
        ];

        foreach ($this->discoverProducts() as $product) {
            $productData = [];
            foreach (self::CHANNELS as $channel) {
                $data = $this->buildChannelData($product, $channel);
                if ($data !== null) {
                    $productData[$channel] = $data;
                }
            }

            if (!empty($productData)) {
                $result['products'][$product] = $productData;
            }
        }

        return $result;
    }

    /**
     * List release files (source archives only, skipping checksums/signatures)
     * for a product release directory.
     *
     * @return array<array{name: string, url: string, size: int}>
     */
    private function listReleaseFiles(string $releaseDir, string $product, string $tag): array
    {
        if (!is_dir($releaseDir)) {
            return [];
        }

        $files = [];
        $entries = scandir($releaseDir) ?: [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            // Skip checksum, signature and zsync files
            if (str_ends_with($entry, '.sha256')
                || str_ends_with($entry, '.asc')
                || str_ends_with($entry, '.zsync')
            ) {
                continue;
            }

            $filePath = $releaseDir . '/' . $entry;
            if (!is_file($filePath)) {
                continue;
            }

            $files[] = [
                'name' => $entry,
                'url'  => self::DOWNLOAD_ROOT_URL . '/' . $product . '/releases/download/' . $tag . '/' . $entry,
                'size' => filesize($filePath) ?: 0,
            ];
        }

        // Stable, deterministic order.
        usort($files, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $files;
    }

    /**
     * Write latest.json to the FTP root directory.
     *
     * Returns the path of the written file.
     */
    public function writeLatestJson(array $data): string
    {
        $path = self::FTP_ROOT . '/latest.json';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($path, $json . "\n");

        return $path;
    }
}
