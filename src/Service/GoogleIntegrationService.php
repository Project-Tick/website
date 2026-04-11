<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoogleIntegrationService
{
    private const API_BASE = 'https://www.googleapis.com';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Get the authenticated user's Google profile from People API.
     */
    public function getUserProfile(string $googleToken): array
    {
        try {
            $response = $this->httpClient->request('GET', self::API_BASE . '/oauth2/v3/userinfo', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $googleToken,
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->warning('Google API error', [
                    'status' => $response->getStatusCode(),
                ]);
                return [];
            }

            return $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('Google API request failed', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}
