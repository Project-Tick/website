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

namespace App\Controller;

use App\Service\JwtService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/license')]
class LicenseApiController extends AbstractController
{
    private JwtService $jwtService;
    private EntityManagerInterface $entityManager;

    public function __construct(JwtService $jwtService, EntityManagerInterface $entityManager)
    {
        $this->jwtService = $jwtService;
        $this->entityManager = $entityManager;
    }

    #[Route('/verify', name: 'api_license_verify', methods: ['POST'])]
    public function verify(Request $request, \Symfony\Component\RateLimiter\RateLimiterFactory $apiVerifyLimiter): JsonResponse
    {
        $limiter = $apiVerifyLimiter->create($request->getClientIp());
        if (false === $limiter->consume(1)->isAccepted()) {
            return new JsonResponse(['error' => 'Too many requests'], 429);
        }

        $data = json_decode($request->getContent(), true);
        $licenseKey = $data['license_key'] ?? null;

        if (!$licenseKey) {
            return new JsonResponse(['status' => 'error', 'message' => 'License key required'], 400);
        }

        // Validate the JWT license key
        $payload = $this->jwtService->validateToken($licenseKey);

        if (!$payload) {
            return new JsonResponse([
                'status' => 'invalid',
                'reason' => 'invalid_signature_or_expired'
            ], 403);
        }

        // --- REVOCATION CHECK ---
        /** @var \App\Repository\UserLicenseRepository $repo */
        $repo = $this->entityManager->getRepository(\App\Entity\UserLicense::class);
        $userLicense = $repo->findOneBy(['licenseKey' => $licenseKey]);

        if (!$userLicense) {
            return new JsonResponse([
                'status' => 'invalid',
                'reason' => 'key_not_found_on_server_or_revoked'
            ], 403);
        }

        return new JsonResponse([
            'status' => 'valid',
            'product' => $payload['product'] ?? 'Generic Product',
            'expires_at' => date('Y-m-d\TH:i:s\Z', $payload['exp']),
            'owner' => $payload['owner'] ?? 'anonymous'
        ]);
    }

    /* Secret endpoint for Admin to generate license tokens */
    #[Route('/generate', name: 'api_license_generate', methods: ['POST'])]
    public function generate(Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $product = $data['product'] ?? 'Generic Product';
        $owner = $data['owner'] ?? 'user_id_unknown';
        $days = $data['duration_days'] ?? 365;

        $token = $this->jwtService->generateToken([
            'product' => $product,
            'owner' => $owner,
            'type' => 'Full-Stack Licensing',
            'v' => '1.0'
        ], $days * 86400);

        return new JsonResponse(['license_key' => $token]);
    }
}
