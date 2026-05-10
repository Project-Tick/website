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
 * Scans the FTP directory for per-product release tag files and resolves the
 * latest release per (product, channel), producing a single latest.json that
 * groups channels (stable / beta / lts) under each product key.
 *
 * Tag format: <product>-<version>[-<suffix>]
 *   stable — <product>-vX.Y.Z              (e.g. meshmc-v7.19.0)
 *   beta   — <product>-YYYYMMDDHHmm-betaN  (e.g. meshmc-202605090000-beta1)
 *   lts    — <product>-YYYYMMDDHHmm-ltsN   (e.g. meshmc-202605090000-lts1)
 *
 * Note: the leading "v" prefix is present only on stable (semver) tags;
 * beta and lts tags use a bare timestamp + suffix.
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
     * Discover all product names that have at least one components file on
     * the FTP. A product is anything matching components-<product>-<rest>.json.
     *
     * @return string[]
     */
    public function discoverProducts(): array
    {
        $files = glob(self::FTP_ROOT . '/components-*.json') ?: [];

        $products = [];
        foreach ($files as $file) {
            $basename = basename($file);
            // components-<product>-<version>[-<suffix>].json
            // Product name is the first dash-delimited segment after "components-".
            if (preg_match('/^components-([a-z][a-z0-9]*)-.+\.json$/i', $basename, $m)) {
                $products[$m[1]] = true;
            }
        }

        $names = array_keys($products);
        sort($names);
        return $names;
    }

    /**
     * Classify a tag's version+suffix portion into a channel.
     *
     * Returns [channel, sortKeyParts] or null if the tag does not match any
     * known channel format.
     *
     * @return array{0: string, 1: array<int|string>}|null
     */
    private function classifyVersionPart(string $versionPart): ?array
    {
        foreach (self::CHANNEL_VERSION_PATTERNS as $channel => $pattern) {
            if (preg_match($pattern, $versionPart, $m)) {
                // Drop full match, keep capture groups as ints for sorting.
                array_shift($m);
                $parts = array_map('intval', $m);
                return [$channel, $parts];
            }
        }
        return null;
    }

    /**
     * Find all components-<product>-*.json files and return the latest release
     * tag for the given (product, channel) pair.
     */
    public function findLatestSnapshotTag(string $product, string $channel = 'stable'): ?string
    {
        if (!in_array($channel, self::CHANNELS, true)) {
            return null;
        }

        $pattern = self::FTP_ROOT . '/components-' . $product . '-*.json';
        $files = glob($pattern) ?: [];

        if (empty($files)) {
            return null;
        }

        $prefix = 'components-' . $product . '-';

        $candidates = []; // list of [tag, sortKeyParts]
        foreach ($files as $file) {
            $basename = basename($file);
            if (!str_starts_with($basename, $prefix) || !str_ends_with($basename, '.json')) {
                continue;
            }

            $versionPart = substr($basename, strlen($prefix), -strlen('.json'));
            $classified = $this->classifyVersionPart($versionPart);
            if ($classified === null || $classified[0] !== $channel) {
                continue;
            }

            $tag = $product . '-' . $versionPart;
            $candidates[] = [$tag, $classified[1]];
        }

        if (empty($candidates)) {
            return null;
        }

        // Sort by capture-group parts descending (works for both semver
        // triples and (date, N) tuples since both are numeric arrays).
        usort($candidates, function (array $a, array $b): int {
            $pa = $a[1];
            $pb = $b[1];
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

        return $candidates[0][0];
    }

    /**
     * Read and parse a components-v*.json file for a given release tag.
     *
     * @return array{schema_version: int, release_tag: string, release_date: string, components: array<string, array{version: string}>}|null
     */
    public function readComponentsJson(string $releaseTag): ?array
    {
        $path = self::FTP_ROOT . '/components-' . $releaseTag . '.json';
        if (!file_exists($path)) {
            return null;
        }

        $data = json_decode(file_get_contents($path), true);
        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * List all component directories on the FTP that contain releases
     * for a given snapshot tag.
     *
     * @return array<string, array{name: string, has_release: bool, download_url: string|null}>
     */
    public function scanComponentDirectories(string $releaseTag): array
    {
        $result = [];
        $entries = scandir(self::FTP_ROOT);

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = self::FTP_ROOT . '/' . $entry;
            if (!is_dir($fullPath)) {
                continue;
            }

            $releaseDir = $fullPath . '/releases/download/' . $releaseTag;
            $hasRelease = is_dir($releaseDir);

            $result[$entry] = [
                'name' => $entry,
                'has_release' => $hasRelease,
                'download_url' => $hasRelease
                    ? self::DOWNLOAD_ROOT_URL . '/' . $entry . '/releases/download/' . $releaseTag . '/'
                    : null,
            ];
        }

        return $result;
    }

    /**
     * Build the per-channel data structure for the given (product, channel).
     *
     * @return array|null
     */
    public function buildChannelData(string $product, string $channel): ?array
    {
        $tag = $this->findLatestSnapshotTag($product, $channel);
        if ($tag === null) {
            return null;
        }

        $components = $this->readComponentsJson($tag);
        if ($components === null) {
            return null;
        }

        $dirs = $this->scanComponentDirectories($tag);

        $componentDownloads = [];
        foreach ($dirs as $dirName => $info) {
            if (!$info['has_release']) {
                continue;
            }

            $entry = [
                'download_url' => $info['download_url'],
            ];

            if (isset($components['components'][$dirName]['version'])) {
                $entry['version'] = $components['components'][$dirName]['version'];
            }

            $releaseDir = self::FTP_ROOT . '/' . $dirName . '/releases/download/' . $tag;
            $files = $this->listReleaseFiles($releaseDir, $dirName, $tag);
            if (!empty($files)) {
                $entry['files'] = $files;
            }

            $componentDownloads[$dirName] = $entry;
        }

        return [
            'release_tag' => $tag,
            'release_date' => $components['release_date'] ?? date('Y-m-d'),
            'components_json_url' => self::DOWNLOAD_ROOT_URL . '/components-' . $tag . '.json',
            'components' => $components['components'] ?? [],
            'downloads' => $componentDownloads,
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
     *
     * @return array
     */
    public function buildLatestJson(): array
    {
        $result = [
            'schema_version' => 2,
            'products' => [],
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
     * for a component release directory.
     *
     * @return array<array{name: string, url: string, size: int}>
     */
    private function listReleaseFiles(string $releaseDir, string $componentName, string $tag): array
    {
        if (!is_dir($releaseDir)) {
            return [];
        }

        $files = [];
        $entries = scandir($releaseDir);

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
                'url' => self::DOWNLOAD_ROOT_URL . '/' . $componentName . '/releases/download/' . $tag . '/' . $entry,
                'size' => filesize($filePath),
            ];
        }

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
