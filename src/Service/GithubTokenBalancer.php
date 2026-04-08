<?php

namespace App\Service;

use Github\Client;
use Psr\Log\LoggerInterface;

class GithubTokenBalancer
{
    private array $tokens = [];
    private string $appId;
    private string $privateKey;
    private LoggerInterface $logger;
    private \Symfony\Contracts\Cache\CacheInterface $cache;
    private int $currentTokenIndex = 0;

    public function __construct(
        LoggerInterface $logger,
        \Symfony\Contracts\Cache\CacheInterface $cache,
        string $appId = '',
        string $privateKey = '',
        string $tokensList = ''
    ) {
        $this->logger = $logger;
        $this->cache = $cache;
        $this->appId = $appId;
        $this->privateKey = $privateKey;
        
        if (!empty($tokensList)) {
            $this->tokens = array_filter(array_map('trim', explode(',', $tokensList)));
        }
        
        // Randomize start index to balance load
        if (count($this->tokens) > 0) {
            $this->currentTokenIndex = rand(0, count($this->tokens) - 1);
        }
    }

    /**
     * Always returns an authenticated client for the Bot (GitHub App).
     * Never falls back to the personal PAT pool.
     */
    public function getBotClient(?int $installationId = null): Client
    {
        if (empty($this->appId) || empty($this->privateKey)) {
            throw new \Exception("GitHub App credentials (ID/Key) are missing for Bot identity.");
        }

        return $this->getAppClient($installationId);
    }

    public function getInstallationToken(?int $installationId): string
    {
        if (!$installationId) {
            throw new \Exception("Installation ID is required to get a bot token.");
        }
        $jwt = $this->generateJwt($this->appId, $this->privateKey);
        
        $cacheKey = "github_inst_token_" . $installationId;
        return $this->cache->get($cacheKey, function (\Symfony\Contracts\Cache\ItemInterface $item) use ($jwt, $installationId) {
            $item->expiresAfter(3300); // 55 minutes
            
            $client = new Client();
            $client->authenticate($jwt, null, \Github\AuthMethod::JWT);
            $tokenData = $client->api('apps')->createInstallationToken($installationId);
            
            return $tokenData['token'];
        });
    }

    /**
     * Always returns an authenticated client from the personal PAT pool.
     * Used for background tasks like commit/contributor scanning.
     */
    public function getScannerClient(): Client
    {
        return $this->getBalancedPatClient();
    }

    /**
     * Compatibility or generic method (optional)
     */
    public function getClient(?int $installationId = null, bool $forcePat = false): Client
    {
        if ($forcePat) {
            return $this->getScannerClient();
        }
        return $this->getBotClient($installationId);
    }

    private function getAppClient(?int $installationId): Client
    {
        $client = new Client();
        $jwt = $this->generateJwt($this->appId, $this->privateKey);
        
        if ($installationId) {
            $cacheKey = "github_inst_token_" . $installationId;
            $token = $this->cache->get($cacheKey, function (\Symfony\Contracts\Cache\ItemInterface $item) use ($jwt, $installationId) {
                $item->expiresAfter(3300); // 55 minutes
                
                $tempClient = new Client();
                $tempClient->authenticate($jwt, null, \Github\AuthMethod::JWT);
                $tokenData = $tempClient->api('apps')->createInstallationToken($installationId);
                
                return $tokenData['token'];
            });
            
            $client->authenticate($token, null, \Github\AuthMethod::ACCESS_TOKEN);
        } else {
            $client->authenticate($jwt, null, \Github\AuthMethod::JWT);
        }
        
        return $client;
    }

    private function getBalancedPatClient(): Client
    {
        if (empty($this->tokens)) {
            throw new \Exception("No GitHub tokens available for fallback.");
        }

        $attempts = count($this->tokens);
        while ($attempts > 0) {
            $token = $this->tokens[$this->currentTokenIndex];
            $client = new Client();
            $client->authenticate($token, null, \Github\AuthMethod::ACCESS_TOKEN);

            try {
                // Quick check for rate limit with caching
                $cacheKey = "github_rate_limit_" . md5($token);
                $remaining = $this->cache->get($cacheKey, function (\Symfony\Contracts\Cache\ItemInterface $item) use ($client) {
                    $item->expiresAfter(60); // Cache for 1 minute
                    $rateLimit = $client->api('rate_limit')->getResource('core');
                    return $rateLimit ? $rateLimit->getRemaining() : 0;
                });
                
                if ($remaining > 10) {
                    return $client;
                }

                $this->logger->info("Token index {$this->currentTokenIndex} has low/no remaining rate limit ($remaining). Rotating...");
            } catch (\Exception $e) {
                $this->logger->error("Error checking rate limit for token index {$this->currentTokenIndex}: " . $e->getMessage());
            }

            // Rotate
            $this->currentTokenIndex = ($this->currentTokenIndex + 1) % count($this->tokens);
            $attempts--;
        }

        // If we reached here, all tokens are low, but just return the last one anyway
        return $client;
    }

    private function generateJwt(string $appId, string $privateKey): string
    {
        $privateKey = $this->cleanPrivateKey($privateKey);
        $header = json_encode(['typ' => 'JWT', 'alg' => 'RS256']);
        $now = time();
        $payload = json_encode([
            'iat' => $now - 60,
            'exp' => $now + (10 * 60),
            'iss' => (int)$appId,
        ]);

        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode($payload);

        $signature = '';
        openssl_sign($base64UrlHeader . "." . $base64UrlPayload, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $base64UrlSignature = $this->base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    private function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private function cleanPrivateKey(string $key): string
    {
        $key = trim($key);
        $key = trim($key, '"\'');
        $key = str_replace(['\r\n', '\n', '\r'], "\n", $key);
        $key = preg_replace('/[^\x20-\x7E\n]/', '', $key);
        return $key;
    }
}
