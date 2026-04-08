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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

#[Route('/api/identity')]
class IdentityController extends AbstractController
{
    private JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    #[Route('/token', name: 'api_identity_token', methods: ['POST', 'GET'])]
    public function getToken(\Doctrine\ORM\EntityManagerInterface $em, Request $request, \Symfony\Component\RateLimiter\RateLimiterFactory $apiIdentityLimiter): JsonResponse
    {
        $limiter = $apiIdentityLimiter->create($request->getClientIp());
        if (false === $limiter->consume(1)->isAccepted()) {
            return new JsonResponse(['error' => 'Too many requests'], 429);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('generate_token', $request->request->get('_token'))) {
                return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
            }
        }
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Not authenticated'], 401);
        }

        // Return existing token if available
        if ($user->getTickApiToken()) {
            return new JsonResponse(['token' => $user->getTickApiToken()]);
        }

        // Generate persistent token (e.g., 10 years)
        $token = $this->jwtService->generateToken([
            'id' => $user->getId(),
            'email' => $user->getUserIdentifier(),
            'roles' => $user->getRoles()
        ], 315360000); // 10 years

        $user->setTickApiToken($token);
        $em->flush();

        return new JsonResponse(['token' => $token]);
    }

    #[Route('/token/regenerate', name: 'api_identity_token_regenerate', methods: ['POST'])]
    public function regenerateToken(\Doctrine\ORM\EntityManagerInterface $em, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('regenerate_token', $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user) return new JsonResponse(['error' => 'Not authenticated'], 401);

        $token = $this->jwtService->generateToken([
            'id' => $user->getId(),
            'email' => $user->getUserIdentifier(),
            'roles' => $user->getRoles()
        ], 315360000);

        $user->setTickApiToken($token);
        $em->flush();

        return new JsonResponse(['token' => $token]);
    }

    #[Route('/me', name: 'api_identity_me', methods: ['GET'])]
    public function me(Request $request, \Doctrine\ORM\EntityManagerInterface $em, \Symfony\Component\RateLimiter\RateLimiterFactory $apiIdentityLimiter): JsonResponse
    {
        $limiter = $apiIdentityLimiter->create($request->getClientIp());
        if (false === $limiter->consume(1)->isAccepted()) {
            return new JsonResponse(['error' => 'Too many requests'], 429);
        }

        // Try various sources for the Authorization header
        $authHeader = $request->headers->get('Authorization') 
            ?? $request->server->get('HTTP_AUTHORIZATION') 
            ?? $request->server->get('REDIRECT_HTTP_AUTHORIZATION');

        $token = null;
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
        } else {
            $token = $request->query->get('token') ?? $request->query->get('access_token');
        }

        if (!$token) {
            return new JsonResponse(['error' => 'Missing token'], 401);
        }

        $payload = $this->jwtService->validateToken($token);
        if (!$payload || !isset($payload['id'])) {
            return new JsonResponse(['error' => 'Invalid or expired token'], 401);
        }

        // --- REVOCATION & EXISTENCE CHECK ---
        /** @var \App\Repository\UserRepository $repo */
        $repo = $em->getRepository(\App\Entity\User::class);
        $user = $repo->find($payload['id']);

        if (!$user || $user->getTickApiToken() !== $token) {
            return new JsonResponse(['error' => 'Token revoked or user no longer exists'], 401);
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'username' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
            'authenticated_via' => 'Tick ID (Verified & Active)'
        ]);
    }
}
