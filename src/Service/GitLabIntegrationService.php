<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GitLabIntegrationService
{
    private const API_BASE = 'https://gitlab.com/api/v4';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Get the authenticated user's GitLab profile.
     */
    public function getUserProfile(string $gitlabToken): array
    {
        return $this->apiGet($gitlabToken, '/user');
    }

    /**
     * List the authenticated user's projects.
     */
    public function getUserProjects(string $gitlabToken, int $perPage = 20): array
    {
        return $this->apiGet($gitlabToken, '/projects', [
            'membership' => 'true',
            'order_by' => 'last_activity_at',
            'per_page' => $perPage,
        ]);
    }

    /**
     * List the authenticated user's groups.
     */
    public function getUserGroups(string $gitlabToken, int $perPage = 20): array
    {
        return $this->apiGet($gitlabToken, '/groups', [
            'per_page' => $perPage,
        ]);
    }

    private function apiGet(string $token, string $path, array $query = []): array
    {
        try {
            $response = $this->httpClient->request('GET', self::API_BASE . $path, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
                'query' => $query,
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->warning('GitLab API error', [
                    'path' => $path,
                    'status' => $response->getStatusCode(),
                ]);
                return [];
            }

            return $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('GitLab API request failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}
