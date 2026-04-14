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
 * Scans the FTP release directory and builds structured asset metadata
 * for the ProjT Launcher updater feed.
 */
class LauncherFeedService
{
    private const FTP_ROOT = '/var/www/ftp/Project-Tick';
    private const DOWNLOAD_ROOT_URL = 'https://ftp.projecttick.org/Project-Tick';
    private const BINARY_PREFIX = 'ProjTLauncher';

    /**
     * Get all assets for a specific release version.
     *
     * @return array<array{
     *     url: string,
     *     name: string,
     *     platform: string,
     *     arch: string,
     *     portable: string,
     *     kind: string,
     *     content_type: string,
     *     size: int,
     *     sha256: string,
     *     qt: string
     * }>
     */
    public function getAssetsForVersion(string $version, string $productFolderName = 'ProjT-Launcher'): array
    {
        $dir = self::FTP_ROOT . '/' . $productFolderName . '/releases/download/' . $version;
        if (!is_dir($dir)) {
            return [];
        }

        $assets = [];
        $files = scandir($dir);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            // Only process ProjTLauncher and MeshMC binaries, skip sha256/asc/zsync and source archives
            if (!str_starts_with($file, 'ProjTLauncher') && !str_starts_with($file, 'MeshMC')) {
                continue;
            }
            if (str_ends_with($file, '.sha256') || str_ends_with($file, '.asc') || str_ends_with($file, '.zsync')) {
                continue;
            }

            // Skip the source tarball (ProjTLauncher-0.0.4-2.tar.gz etc)
            if (preg_match('/^(ProjTLauncher|MeshMC)-\d+\.\d+/', $file)) {
                // This catches source archives, but we need to NOT catch platform binaries
                if (!str_contains($file, 'Linux') && !str_contains($file, 'Windows') && !str_contains($file, 'macOS')) {
                    continue;
                }
            }

            $filePath = $dir . '/' . $file;
            $parsed = $this->parseFilename($file, $version);
            if ($parsed === null) {
                continue;
            }

            // Read SHA256
            $sha256 = '';
            $sha256File = $filePath . '.sha256';
            if (file_exists($sha256File)) {
                $sha256 = trim(file_get_contents($sha256File));
                // Some sha256 files contain "hash  filename" format
                if (str_contains($sha256, ' ')) {
                    $sha256 = explode(' ', $sha256)[0];
                }
            }

            $size = filesize($filePath);

            $assets[] = [
                'url' => self::DOWNLOAD_ROOT_URL . '/' . $productFolderName . '/releases/download/' . $version . '/' . $file,
                'name' => $file,
                'platform' => $parsed['platform'],
                'arch' => $parsed['arch'],
                'portable' => $parsed['portable'] ? 'true' : 'false',
                'kind' => $parsed['kind'],
                'content_type' => $this->guessContentType($file),
                'size' => $size,
                'sha256' => $sha256,
                'qt' => $parsed['qt'],
            ];
        }

        // Sort: linux first, then macOS, then windows; within each platform sort by arch
        usort($assets, function ($a, $b) {
            $platformOrder = ['linux' => 0, 'macos' => 1, 'windows' => 2];
            $pa = $platformOrder[$a['platform']] ?? 9;
            $pb = $platformOrder[$b['platform']] ?? 9;
            if ($pa !== $pb) {
                return $pa - $pb;
            }
            return strcmp($a['name'], $b['name']);
        });

        return $assets;
    }

    /**
     * Get all available release versions, sorted newest-first.
     * When a release directory contains components.json, uses its component
     * version for sorting; otherwise falls back to directory name.
     *
     * @return string[]
     */
    public function getAvailableVersions(string $productFolderName = 'ProjT-Launcher'): array
    {
        $baseDir = self::FTP_ROOT . '/' . $productFolderName . '/releases/download';
        if (!is_dir($baseDir)) {
            return [];
        }

        $dirs = array_filter(scandir($baseDir), function ($d) use ($baseDir) {
            return $d !== '.' && $d !== '..' && is_dir($baseDir . '/' . $d);
        });

        // Build sortable version for each directory
        $versionMap = [];
        foreach ($dirs as $dir) {
            $versionMap[$dir] = $this->resolveComponentVersion($baseDir . '/' . $dir, $dir);
        }

        // Sort by resolved version, newest first
        usort($dirs, function ($a, $b) use ($versionMap) {
            return version_compare(
                $this->normalizeVersion($versionMap[$b]),
                $this->normalizeVersion($versionMap[$a])
            );
        });

        return array_values($dirs);
    }

    /**
     * Read the MeshMC component version from components.json in a release
     * directory. Falls back to the directory name if not available.
     */
    private function resolveComponentVersion(string $releaseDir, string $fallback): string
    {
        $componentsFile = $releaseDir . '/components.json';
        if (file_exists($componentsFile)) {
            $data = json_decode(file_get_contents($componentsFile), true);
            if (is_array($data) && isset($data['components']['meshmc']['version'])) {
                return $data['components']['meshmc']['version'];
            }
        }
        return $fallback;
    }

    /**
     * Normalize version string for comparison.
     * Converts "0.0.4-2" to "0.0.4.2" so version_compare works correctly.
     */
    private function normalizeVersion(string $version): string
    {
        return str_replace('-', '.', $version);
    }

    /**
     * Parse a binary filename into platform/arch/portable/kind/qt metadata.
     */
    private function parseFilename(string $filename, string $version): ?array
    {
        $result = [
            'platform' => 'unknown',
            'arch' => 'x86_64',
            'portable' => false,
            'kind' => 'archive',
            'qt' => '5',
        ];

        $lower = strtolower($filename);

        // --- Platform ---
        if (str_contains($lower, 'linux')) {
            $result['platform'] = 'linux';
        } elseif (str_contains($lower, 'macos')) {
            $result['platform'] = 'macos';
        } elseif (str_contains($lower, 'windows')) {
            $result['platform'] = 'windows';
        } else {
            return null; // Unknown platform
        }

        // --- Architecture ---
        if (str_contains($lower, 'aarch64') || str_contains($lower, 'arm64')) {
            $result['arch'] = 'aarch64';
        } elseif (str_contains($lower, 'x86_64') || str_contains($lower, 'w64') || str_contains($lower, 'msvc')) {
            // w64 (MinGW-w64) and plain MSVC are x86_64 unless arm64 is specified
            if (!str_contains($lower, 'arm64') && !str_contains($lower, 'aarch64')) {
                $result['arch'] = 'x86_64';
            }
        }

        // --- Portable ---
        if (str_contains($lower, 'portable')) {
            $result['portable'] = true;
        }

        // --- Kind ---
        if (str_ends_with($lower, '.appimage')) {
            $result['kind'] = 'appimage';
        } elseif (str_ends_with($lower, '.exe')) {
            $result['kind'] = 'installer';
        } elseif (str_ends_with($lower, '.zip') || str_ends_with($lower, '.tar.gz') || str_ends_with($lower, '.tar.xz')) {
            $result['kind'] = 'archive';
        } else {
            $result['kind'] = 'archive';
        }

        // --- Qt version ---
        if (str_contains($lower, 'qt6')) {
            $result['qt'] = '6';
        } elseif (str_contains($lower, 'qt5')) {
            $result['qt'] = '5';
        } else {
            // Default: infer from toolchain naming conventions
            // MinGW-w64 and MSVC default to Qt 6 in modern builds
            $result['qt'] = '6';
        }

        // --- Windows: differentiate MinGW vs MSVC as a "variant" embedded in the name ---
        // The name field already carries this info, no need for extra metadata

        return $result;
    }

    /**
     * Guess the MIME content type from a filename.
     */
    private function guessContentType(string $filename): string
    {
        $lower = strtolower($filename);

        if (str_ends_with($lower, '.tar.gz')) {
            return 'application/gzip';
        }
        if (str_ends_with($lower, '.tar.xz')) {
            return 'application/x-xz';
        }
        if (str_ends_with($lower, '.zip')) {
            return 'application/zip';
        }
        if (str_ends_with($lower, '.exe')) {
            return 'application/vnd.microsoft.portable-executable';
        }
        if (str_ends_with($lower, '.appimage')) {
            return 'application/x-executable';
        }
        if (str_ends_with($lower, '.dmg')) {
            return 'application/x-apple-diskimage';
        }

        return 'application/octet-stream';
    }
}
