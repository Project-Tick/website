<?php

namespace App\Service;

class JwtService
{
    private string $secret;

    public function __construct(string $secret = '')
    {
        if ($secret === '') {
            $secret = getenv('JWT_SECRET') ?: ($_ENV['JWT_SECRET'] ?? '');
        }
        if ($secret === '') {
            throw new \RuntimeException('JWT_SECRET environment variable is not set.');
        }
        $this->secret = $secret;
    }

    public function generateToken(array $payload, int $expiry = 3600): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        
        $payload['exp'] = time() + $expiry;
        $payload['iat'] = time();
        $payload_encoded = json_encode($payload);

        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload_encoded));

        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $this->secret, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }

    public function validateToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        list($base64Header, $base64Payload, $base64Signature) = $parts;

        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $this->secret, true);
        $validSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        if (!hash_equals($base64Signature, $validSignature)) return null;

        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $base64Payload)), true);
        
        if (isset($payload['exp']) && $payload['exp'] < time()) return null;

        return $payload;
    }
}
