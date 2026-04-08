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
use App\Service\MinecraftAuthService;
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

class MicrosoftAuthenticator extends OAuth2Authenticator
{
    use TargetPathTrait;

    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $entityManager,
        private RouterInterface $router,
        private MinecraftAuthService $minecraftAuthService,
        private Security $security
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_microsoft_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('microsoft');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client, $request) {
                // Microsoft Graph API'sine gitmek yerine (ki o IDX14100 hatası veriyor), 
                // doğrudan id_token içindeki JWT'yi çözüyoruz.
                $values = $accessToken->getValues();
                $idToken = $values['id_token'] ?? null;

                if (!$idToken) {
                    throw new AuthenticationException('No ID Token received from Microsoft. Ensure openid scope is present.');
                }

                // JWT Payload decoding
                $parts = explode('.', $idToken);
                if (count($parts) < 2) {
                    throw new AuthenticationException('Invalid ID Token format.');
                }

                $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
                
                // Microsoft v2.0 id_token fieldları: oid (object id), preferred_username (email)
                $msId = $payload['oid'] ?? $payload['sub'] ?? null;
                $email = $payload['email'] ?? $payload['preferred_username'] ?? $payload['upn'] ?? null;
                $displayName = $payload['name'] ?? null;

                if (!$msId || !$email) {
                    throw new AuthenticationException('Could not extract identity (OID/Email) from Microsoft ID Token.');
                }

                // 2. Exchange MS Access Token for Minecraft Data (UUID için)
                try {
                    // Bu kısım Xbox Live sunucularıyla konuşur, Graph ile değil.
                    $minecraftData = $this->minecraftAuthService->authenticateWithMicrosoft($accessToken->getToken());
                    $request->getSession()->set('minecraft_auth', $minecraftData);
                } catch (\Exception $e) {
                    // Minecraft hesabı olmasa da siteye giriş yapabilsin diye hata yutulabilir
                    $minecraftData = null;
                }

                // 3. Kullanıcıyı Bul veya Bağla
                $currentUser = $this->security->getUser();
                $user = $this->entityManager->getRepository(User::class)->findOneBy(['microsoftId' => $msId]);

                if ($currentUser) {
                    // Kullanıcı zaten giriş yapmış, hesabı bağlıyoruz.
                    /** @var User $user */
                    $user = $currentUser;
                    $user->setMicrosoftId($msId);
                } elseif (!$user) {
                    // Giriş yapmamış ve bu MS ID ile eşleşen hesap yok, yeni oluştur veya email ile bul.
                    $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
                    if (!$user) {
                        $user = new User();
                        $user->setEmail($email);
                        $user->setRoles(['ROLE_USER']);
                        $user->setUsername($displayName ?? explode('@', $email)[0]);
                    }
                    $user->setMicrosoftId($msId);
                }

                // Minecraft verisi varsa UUID ve Avatar güncelle
                if ($minecraftData && isset($minecraftData['uuid'])) {
                    $user->setMinecraftUuid($minecraftData['uuid']);
                    $user->setMinecraftAccessToken($minecraftData['minecraft_token'] ?? null);
                    $user->setMinecraftUsername($minecraftData['username'] ?? null);
                    if (!$user->getAvatar()) {
                        $user->setAvatar('https://mc-heads.net/avatar/' . $minecraftData['uuid']);
                    }
                }

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
        $request->getSession()->getFlashBag()->add('error', 'Login failed: ' . $exception->getMessage());
        return new RedirectResponse($this->router->generate('app_login'));
    }
}
