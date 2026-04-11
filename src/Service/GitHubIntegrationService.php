<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GitHubIntegrationService
{
    private const API_BASE = 'https://api.github.com';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Get the authenticated user's GitHub profile.
     */
    public function getUserProfile(string $githubToken): array
    {
        return $this->apiGet($githubToken, '/user');
    }

    /**
     * List the authenticated user's public repositories.
     */
    public function getUserRepos(string $githubToken, int $perPage = 30): array
    {
        return $this->apiGet($githubToken, '/user/repos', [
            'sort' => 'updated',
            'per_page' => $perPage,
            'type' => 'owner',
        ]);
    }

    /**
     * Check if the authenticated user is a member of a specific organization.
     */
    public function checkOrgMembership(string $githubToken, string $org): bool
    {
        try {
            $response = $this->httpClient->request('GET', self::API_BASE . '/user/memberships/orgs/' . urlencode($org), [
                'headers' => $this->headers($githubToken),
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Throwable $e) {
            $this->logger->warning('GitHub org membership check failed', [
                'org' => $org,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get a user's contribution events (public).
     */
    public function getUserEvents(string $githubToken, string $username, int $perPage = 10): array
    {
        return $this->apiGet($githubToken, '/users/' . urlencode($username) . '/events/public', [
            'per_page' => $perPage,
        ]);
    }

    private function apiGet(string $token, string $path, array $query = []): array
    {
        try {
            $response = $this->httpClient->request('GET', self::API_BASE . $path, [
                'headers' => $this->headers($token),
                'query' => $query,
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->warning('GitHub API error', [
                    'path' => $path,
                    'status' => $response->getStatusCode(),
                ]);
                return [];
            }

            return $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('GitHub API request failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function headers(string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ];
    }
}
