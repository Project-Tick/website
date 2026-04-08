<?php

namespace App\Service;

use Github\Client;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class GithubService
{
    private Client $client;
    private CacheInterface $cache;

    public function __construct(CacheInterface $cache)
    {
        $this->client = new Client();
        
        $token = $_ENV['GITHUB_TOKEN'] ?? null;
        if ($token) {
            $this->client->authenticate($token, null, \Github\AuthMethod::ACCESS_TOKEN);
        }

        $this->cache = $cache;
    }

    public function getContributors(string $repoUrl): array
    {
        // Parse owner and repo from URL (e.g. https://github.com/Project-Tick/ProjT-Launcher)
        $path = parse_url($repoUrl, PHP_URL_PATH);
        $parts = explode('/', trim($path, '/'));

        if (count($parts) < 2) {
            return [];
        }

        $owner = $parts[0];
        $repo = $parts[1];
        $cacheKey = sprintf('github_contributors_%s_%s_v2', $owner, $repo);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($owner, $repo) {
            $item->expiresAfter(86400); // Cache for 24 hours

            try {
                // Get contributors
                // We request up to 500. The API might paginate.
                // The library's ResultPager is best for fetching all/many.
                
                $paginator = new \Github\ResultPager($this->client);
                $parameters = [$owner, $repo];
                
                // Fetch all contributors (automatically paginates)
                $contributors = $paginator->fetchAll($this->client->api('repo'), 'contributors', $parameters);
                
                // Sort by contributions desc (API usually does this but to be safe)
                // Limit to 500
                return array_slice($contributors, 0, 500); 
            } catch (\Exception $e) {
                // In case of error (rate limit, etc.), return empty array
                // Log error if logger was available
                return [];
            }
        });
    }
}
