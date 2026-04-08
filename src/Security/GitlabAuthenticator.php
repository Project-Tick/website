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

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class GitlabAuthenticator extends OAuth2Authenticator
{
    use TargetPathTrait;

    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $entityManager,
        private RouterInterface $router,
        private Security $security
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_gitlab_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('gitlab');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
                /** @var \League\OAuth2\Client\Provider\GenericResourceOwner $gitlabUser */
                $gitlabUser = $client->fetchUserFromToken($accessToken);

                // Generic provider returns data via toArray() or getId()
                $userData = $gitlabUser->toArray();
                
                // GitLab returns 'id', 'email', 'username' (or 'nickname')
                // Note: Generic provider may map fields differently.
                // Usually it maps 'id' to getId().
                
                $gitlabId = (string) $gitlabUser->getId();
                // Depending on generic provider config, email might be in toArray() called 'email'
                $email = $userData['email'] ?? null;
                $username = $userData['username'] ?? ($userData['nickname'] ?? null);

                if (!$email) {
                    throw new AuthenticationException('Email not returned from GitLab');
                }

                // 1. Check if we already have a user logged in! (Linking mode)
                $currentUser = $this->security->getUser();
                if ($currentUser instanceof User) {
                    $currentUser->setGitlabId($gitlabId);
                    $currentUser->setGitlabUsername($username);
                    $this->entityManager->persist($currentUser);
                    $this->entityManager->flush();
                    return $currentUser;
                }

                // 2. Not logged in, search by GitLab ID
                $user = $this->entityManager->getRepository(User::class)->findOneBy(['gitlabId' => $gitlabId]);

                if (!$user) {
                    // 3. Search by Email
                    $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
                    if (!$user) {
                        $user = new User();
                        $user->setEmail($email);
                        $user->setRoles(['ROLE_USER']);
                    }
                    $user->setGitlabId($gitlabId);
                }

                $user->setGitlabUsername($username);
                $this->entityManager->persist($user);
                $this->entityManager->flush();

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $targetPath = $this->getTargetPath($request->getSession(), $firewallName);

        if ($targetPath) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->router->generate('app_successful_login'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = str_replace(['+', '%20'], ' ', $exception->getMessageKey());

        return new Response($message, Response::HTTP_FORBIDDEN);
    }
}
