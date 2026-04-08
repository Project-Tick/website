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

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GitlabService
{
    private HttpClientInterface $httpClient;
    private CacheInterface $cache;

    public function __construct(HttpClientInterface $httpClient, CacheInterface $cache)
    {
        $this->httpClient = $httpClient;
        $this->cache = $cache;
    }

    public function getContributors(string $repoUrl): array
    {
        // Example URL: https://gitlab.com/project-tick/projt-launcher
        $parsed = parse_url($repoUrl);
        if (!$parsed || !isset($parsed['host']) || !isset($parsed['path'])) {
            return [];
        }

        $host = $parsed['host'];
        $path = trim($parsed['path'], '/');
        // Remove .git if present
        $path = preg_replace('/\.git$/', '', $path);
        
        // Convert to URL-encoded path for GitLab API (e.g. project-tick%2Fprojt-launcher)
        $projectPath = urlencode($path);
        
        $cacheKey = sprintf('gitlab_contributors_%s_%s', md5($host), md5($projectPath));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($host, $projectPath) {
            $item->expiresAfter(86400); // Cache for 24 hours

            try {
                // Determine API endpoint
                $apiUrl = sprintf('https://%s/api/v4/projects/%s/repository/contributors', $host, $projectPath);
                
                $response = $this->httpClient->request('GET', $apiUrl, [
                    'query' => [
                        'per_page' => 100,
                        'order_by' => 'commits',
                        'sort' => 'desc',
                    ]
                ]);

                if ($response->getStatusCode() !== 200) {
                    return [];
                }

                $data = $response->toArray();
                
                $contributors = [];
                // Transform to match Github contributor structure slightly roughly for TWIG
                // GitLab returns name, email, commits, but often we don't get the avatar directly unless we fetch users
                foreach ($data as $contrib) {
                    $email = trim($contrib['email'] ?? '');
                    $emailPrefix = $email ? explode('@', $email)[0] : '';
                    $emailHash = md5(strtolower($email));
                    $contributors[] = [
                        'login' => $contrib['name'] ?? 'Unknown',
                        'name' => $contrib['name'] ?? 'Unknown',
                        'email_prefix' => $emailPrefix,
                        'avatar_url' => 'https://www.gravatar.com/avatar/' . $emailHash . '?d=identicon', // Fallback to gravatar
                        'html_url' => $emailPrefix ? 'https://gitlab.com/' . $emailPrefix : '#',
                        'is_gitlab' => true,
                        'contributions' => $contrib['commits'] ?? 0,
                    ];
                }

                return $contributors;
            } catch (\Exception $e) {
                return [];
            }
        });
    }
}
