<?php

namespace App\Service;

/**
 * Project Tick Runtime Encryption Engine
 */
class TickCrypto
{
    private const ENCRYPTION_ALGO = 'aes-256-cbc';

    private static function getEncryptionKey(): string
    {
        $key = getenv('TICK_ENCRYPTION_KEY') ?: ($_ENV['TICK_ENCRYPTION_KEY'] ?? '');
        if ($key === '') {
            die("Project Tick Security Exception: TICK_ENCRYPTION_KEY environment variable is not set.\n");
        }
        return $key;
    }

    /**
     * Used by the Build Script to compile and encrypt the raw PHP code.
     */
    public static function encrypt(string $phpCode): string
    {
        // Strip opening php tag since we will run it via eval()
        $phpCode = preg_replace('/^<\?php\s*/i', '', $phpCode);
        
        $ivLength = openssl_cipher_iv_length(self::ENCRYPTION_ALGO);
        $iv = openssl_random_pseudo_bytes($ivLength);
        
        $key = hash('sha256', self::getEncryptionKey(), true);
        
        $encrypted = openssl_encrypt($phpCode, self::ENCRYPTION_ALGO, $key, OPENSSL_RAW_DATA, $iv);
        
        return base64_encode($iv . $encrypted);
    }

    /**
     * Used at runtime to decrypt the compiled files if the license is valid.
     */
    public static function decrypt(string $payload): string
    {
        // 1. Check License Natively for Autoloader Context
        // Resolves from src/Service to root directory
        $projectDir = dirname(__DIR__, 2);
        $info = self::verifyLicense($projectDir);
        
        if (!in_array($info['status'], ['VERIFIED', 'DEV_MODE', 'OFFICIAL_INFRA'])) {
            die("Project Tick Security Exception: HALTED! The Enterprise Module cannot run because the RSA License is invalid, missing, or the domain does not match.\n");
        }

        if (!in_array($info['type'], ['PRO', 'ENTERPRISE', 'SAAS', 'OFFICIAL'])) {
            die("Project Tick Security Exception: HALTED! This environment is running Community Edition. Enterprise encrypted modules are locked.\n");
        }

        // 2. Perform AES Decryption
        $data = base64_decode($payload);
        $ivLength = openssl_cipher_iv_length(self::ENCRYPTION_ALGO);
        $iv = substr($data, 0, $ivLength);
        $encryptedCode = substr($data, $ivLength);
        $key = hash('sha256', self::getEncryptionKey(), true);

        $decrypted = openssl_decrypt($encryptedCode, self::ENCRYPTION_ALGO, $key, OPENSSL_RAW_DATA, $iv);
        
        if ($decrypted === false) {
             die("Project Tick Security Exception: Data corruption detected in compiled module.\n");
        }

        return $decrypted;
    }

    /**
     * Static license verifier (Duplicated safely from ModuleService for isolated runtime)
     */
    public static function verifyLicense(string $projectDir): array
    {
        $info = [
            'status' => 'COMMUNITY_MODE',
            'type' => 'COMMUNITY',
            'domain' => null,
        ];

        if (isset($_SERVER['APP_ENV']) && $_SERVER['APP_ENV'] === 'dev') {
            $info['status'] = 'DEV_MODE';
            $info['type'] = 'ENTERPRISE';
            return $info;
        }

        $licensePath = $projectDir . '/LICENSE_KEY';
        if (file_exists($licensePath)) {
            $info['status'] = 'INVALID_SIGNATURE';
            $rawKey = trim(file_get_contents($licensePath));
            
            if (str_contains($rawKey, '.')) {
                [$payload, $signatureBase64] = explode('.', $rawKey, 2);
                $signature = base64_decode($signatureBase64);
                
                $publicKey = "-----BEGIN PUBLIC KEY-----\n" .
                    "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAj1yMXn9G56PLnW4vrF0A\n" .
                    "1GXAZ6co3csElpLyFKGIqXmePen7OsD2neW5ljxNDI2o9s9sFotfXp/g+UVXcEYM\n" .
                    "Dcnx91WwXHsq3Fwep9NdaF8r+asfh/DNElo0RrrUKjIDC3d/bADklqJ1lsSUYTYP\n" .
                    "ZnfVcG/eC0A3HaP0ymztOefOJmIFEx1wluHFlJbx3uzGIrWLZTeJE3Tu+rCwhEj2\n" .
                    "UYxKbfmFHvhGu+Eln8KIYJI+IFRWFjNtBpU8LUyAva/lFedwLowgy7OLNkl2pjKd\n" .
                    "ovQyCMcxDZG+Vdj4EPkMFP7cHUT7CB3zohTTjZ8719/ojZGVUGDESqew0Nqr6olM\n" .
                    "zwIDAQAB\n" .
                    "-----END PUBLIC KEY-----";

                if (openssl_verify($payload, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1) {
                    if (str_starts_with($payload, 'PT-INTERNAL-PRO')) {
                        $parts = explode('-', $payload);
                        $info['type'] = $parts[2] ?? 'PRO';
                        $info['domain'] = $parts[5] ?? null;
                        
                        // Domain Verification Check
                        if ($info['domain'] !== null && php_sapi_name() !== 'cli') {
                            $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
                            $currentDomain = explode(':', $currentHost)[0];
                            
                            if (strtolower($currentDomain) !== strtolower($info['domain'])) {
                                $info['status'] = 'INVALID_DOMAIN';
                                return $info;
                            }
                        }

                        $info['status'] = 'VERIFIED';
                    }
                }
            }
        }

        if (!in_array($info['status'], ['VERIFIED', 'DEV_MODE'])) {
            $masterIp = '152.53.231.231';
            $localIp = $_SERVER['SERVER_ADDR'] ?? '';
            
            if ($localIp === $masterIp || file_exists('/var/www/projt-website/config/jwt/projt_private.pem')) {
                $info['status'] = 'OFFICIAL_INFRA';
                $info['type'] = 'ENTERPRISE';
            }
        }

        return $info;
    }
}
