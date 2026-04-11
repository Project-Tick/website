<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MicrosoftXboxService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Authenticate with Xbox Live using a Microsoft access token.
     *
     * @return array{Token: string, DisplayClaims: array}
     */
    public function getXboxLiveToken(string $microsoftAccessToken): array
    {
        $response = $this->httpClient->request('POST', 'https://user.auth.xboxlive.com/user/authenticate', [
            'json' => [
                'Properties' => [
                    'AuthMethod' => 'RPS',
                    'SiteName' => 'user.auth.xboxlive.com',
                    'RpsTicket' => 'd=' . $microsoftAccessToken,
                ],
                'RelyingParty' => 'http://auth.xboxlive.com',
                'TokenType' => 'JWT',
            ],
        ]);

        return $response->toArray();
    }

    /**
     * Obtain an XSTS token for Minecraft services using an Xbox Live token.
     *
     * @return array{Token: string, DisplayClaims: array}
     */
    public function getXstsToken(string $xblToken): array
    {
        $response = $this->httpClient->request('POST', 'https://xsts.auth.xboxlive.com/xsts/authorize', [
            'json' => [
                'Properties' => [
                    'SandboxId' => 'RETAIL',
                    'UserTokens' => [$xblToken],
                ],
                'RelyingParty' => 'rp://api.minecraftservices.com/',
                'TokenType' => 'JWT',
            ],
        ]);

        return $response->toArray();
    }

    /**
     * Exchange XSTS token for a Minecraft access token.
     *
     * @return array{access_token: string, token_type: string, expires_in: int}
     */
    public function getMinecraftToken(string $xstsToken, string $userHash): array
    {
        $response = $this->httpClient->request('POST', 'https://api.minecraftservices.com/authentication/login_with_xbox', [
            'json' => [
                'identityToken' => sprintf('XBL3.0 x=%s;%s', $userHash, $xstsToken),
            ],
        ]);

        return $response->toArray();
    }

    /**
     * Get Minecraft profile using a Minecraft access token. Cached for 5 minutes.
     *
     * @return array{id: string, name: string, skins: array, capes: array}
     */
    public function getMinecraftProfile(string $minecraftToken): array
    {
        $cacheKey = 'mc_profile_' . hash('sha256', $minecraftToken);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($minecraftToken): array {
            $item->expiresAfter(300); // 5 minute TTL

            $response = $this->httpClient->request('GET', 'https://api.minecraftservices.com/minecraft/profile', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $minecraftToken,
                ],
            ]);

            return $response->toArray();
        });
    }

    /**
     * Check Minecraft game entitlements.
     */
    public function checkEntitlements(string $minecraftToken): array
    {
        $response = $this->httpClient->request('GET', 'https://api.minecraftservices.com/entitlements/mcstore', [
            'headers' => [
                'Authorization' => 'Bearer ' . $minecraftToken,
            ],
        ]);

        return $response->toArray();
    }

    /**
     * Full authentication chain: Microsoft token → Xbox Live → XSTS → Minecraft profile.
     *
     * @return array{
     *     minecraft_token: string,
     *     username: string,
     *     uuid: string|null,
     *     skins: array,
     *     owns_game: bool,
     *     expires_in: int
     * }
     *
     * @throws \RuntimeException on authentication chain failure
     */
    public function authenticateMinecraft(string $microsoftAccessToken): array
    {
        try {
            // Step 1: Xbox Live
            $xblData = $this->getXboxLiveToken($microsoftAccessToken);
            $xblToken = $xblData['Token'];
            $userHash = $xblData['DisplayClaims']['xui'][0]['uhs'];

            // Step 2: XSTS
            $xstsData = $this->getXstsToken($xblToken);
            $xstsToken = $xstsData['Token'];

            // Step 3: Minecraft token
            $mcAuth = $this->getMinecraftToken($xstsToken, $userHash);
            $mcToken = $mcAuth['access_token'];

            // Step 4: Entitlements
            $entitlements = $this->checkEntitlements($mcToken);

            // Step 5: Profile
            $profile = $this->getMinecraftProfile($mcToken);

            return [
                'minecraft_token' => $mcToken,
                'username' => $profile['name'] ?? 'Unknown',
                'uuid' => $profile['id'] ?? null,
                'skins' => $profile['skins'] ?? [],
                'owns_game' => !empty($entitlements['items']),
                'expires_in' => $mcAuth['expires_in'] ?? 86400,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Minecraft authentication chain failed', [
                'step' => 'chain',
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Minecraft authentication failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
