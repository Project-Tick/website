<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class KeycloakBrokerService
{
    private const BASE_URL = 'https://id.projecttick.net';
    private const REALM = 'projecttick';

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        private LoggerInterface $logger,
        private string $keycloakClientId,
        private string $keycloakClientSecret,
        private string $keycloakAdminUser,
        private string $keycloakAdminPassword,
    ) {
    }

    /**
     * Get an admin access token from Keycloak (cached for its lifetime minus a buffer).
     */
    private function getAdminToken(): string
    {
        return $this->cache->get('keycloak_admin_token', function (ItemInterface $item): string {
            $response = $this->httpClient->request('POST', self::BASE_URL . '/realms/master/protocol/openid-connect/token', [
                'body' => [
                    'grant_type' => 'password',
                    'client_id' => 'admin-cli',
                    'username' => $this->keycloakAdminUser,
                    'password' => $this->keycloakAdminPassword,
                ],
            ]);

            $data = $response->toArray();
            // Cache for token lifetime minus 30s buffer
            $item->expiresAfter(max(($data['expires_in'] ?? 60) - 30, 10));

            return $data['access_token'];
        });
    }

    /**
     * Get broker (IdP) token for a specific provider, using admin API.
     *
     * @return string|null The provider's access token, or null on failure
     */
    public function getBrokerToken(string $keycloakUserId, string $provider): ?string
    {
        try {
            $adminToken = $this->getAdminToken();

            $response = $this->httpClient->request(
                'GET',
                self::BASE_URL . '/admin/realms/' . self::REALM . '/users/' . $keycloakUserId . '/federated-identity/' . $provider . '/token',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $adminToken,
                    ],
                ]
            );

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                $this->logger->warning('Keycloak broker token request failed', [
                    'provider' => $provider,
                    'status' => $statusCode,
                ]);
                return null;
            }

            // Response is typically plain text access_token or JSON depending on provider
            $content = $response->getContent();
            $decoded = json_decode($content, true);

            if (is_array($decoded) && isset($decoded['access_token'])) {
                return $decoded['access_token'];
            }

            // Some providers return the token directly as text
            return $content ?: null;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to retrieve broker token', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get all linked identity providers for a Keycloak user.
     *
     * @return array<array{identityProvider: string, userId: string, userName: string}>
     */
    public function getLinkedProviders(string $keycloakUserId): array
    {
        try {
            $adminToken = $this->getAdminToken();

            $response = $this->httpClient->request(
                'GET',
                self::BASE_URL . '/admin/realms/' . self::REALM . '/users/' . $keycloakUserId . '/federated-identity',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $adminToken,
                    ],
                ]
            );

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            return $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('Failed to retrieve linked providers', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Check if a specific provider is linked for a user.
     */
    public function isProviderLinked(string $keycloakUserId, string $provider): bool
    {
        $linked = $this->getLinkedProviders($keycloakUserId);

        foreach ($linked as $entry) {
            if (($entry['identityProvider'] ?? '') === $provider) {
                return true;
            }
        }

        return false;
    }
}
