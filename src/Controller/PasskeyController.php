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

use App\Entity\PublicCredential;
use App\Repository\PublicCredentialRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Webauthn\Bundle\Service\PublicKeyCredentialCreationOptionsFactory;
use Webauthn\Bundle\Service\PublicKeyCredentialRequestOptionsFactory;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialRequestOptions;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use App\Security\GithubAuthenticator;
use Symfony\Component\Serializer\SerializerInterface;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/passkey')]
class PasskeyController extends AbstractController
{
    private SerializerInterface $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    #[Route('/login/options', name: 'app_passkey_login_options', methods: ['POST'])]
    public function loginOptions(Request $request, PublicKeyCredentialRequestOptionsFactory $factory, PublicCredentialRepository $repository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        $userEntity = null;
        if ($email) {
            $userEntity = $repository->findOneByUsername($email);
        }

        $options = $factory->create(
            'default',
            [], // allowedCredentials (empty means any)
            PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            $userEntity ? ['rpId' => $request->getHost(), 'userHandle' => base64_encode($userEntity->id)] : ['rpId' => $request->getHost()]
        );

        $request->getSession()->set('webauthn_login_options', $options);

        return new JsonResponse($this->serializer->serialize($options, 'json'), 200, [], true);
    }

    #[Route('/login/verify', name: 'app_passkey_login_verify', methods: ['POST'])]
    public function loginVerify(
        Request $request,
        PublicKeyCredentialLoader $loader,
        AuthenticatorAssertionResponseValidator $validator,
        PublicCredentialRepository $repository,
        EntityManagerInterface $em,
        UserAuthenticatorInterface $userAuthenticator,
        \Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator $formLoginAuthenticator
    ): JsonResponse {
        $options = $request->getSession()->get('webauthn_login_options');
        if (!$options instanceof PublicKeyCredentialRequestOptions) {
            return new JsonResponse(['error' => 'No login options found'], 400);
        }

        try {
            $content = $request->getContent();
            $publicKeyCredential = $loader->load($content);
            $response = $publicKeyCredential->getResponse();

            if (!$response instanceof AuthenticatorAssertionResponse) {
                return new JsonResponse(['error' => 'Invalid response type'], 400);
            }

            $credentialSource = $repository->findOneByCredentialId($publicKeyCredential->getRawId());
            if (!$credentialSource) {
                return new JsonResponse(['error' => 'Unknown credential'], 400);
            }

            $validator->check(
                $publicKeyCredential->getRawId(),
                $response,
                $options,
                $request->getHost(),
                $credentialSource->getUserHandle()
            );

            $user = $em->getRepository(\App\Entity\User::class)->find($credentialSource->getUserHandle());
            
            $userAuthenticator->authenticateUser(
                $user,
                $formLoginAuthenticator, 
                $request
            );

            return new JsonResponse(['success' => true]);
        } catch (\Throwable $e) {
            $this->container->get('logger')->error('Passkey Login Error: ' . $e->getMessage());
            return new JsonResponse(['error' => 'Server Error: ' . $e->getMessage()], 400);
        }
    }

    #[Route('/register/options', name: 'app_passkey_register_options', methods: ['POST'])]
    public function registerOptions(Request $request, PublicKeyCredentialCreationOptionsFactory $factory): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user || !$user instanceof \App\Entity\User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $userEntity = new PublicKeyCredentialUserEntity(
            $user->getEmail(),
            base64_encode((string) $user->getId()), // Base64 encode for JS safeBase64ToBuffer
            $user->getUsername() ?? $user->getEmail(),
            null
        );

        $excludeCredentials = [];
        foreach ($user->getPublicCredentials() as $credential) {
            $excludeCredentials[] = new \Webauthn\PublicKeyCredentialDescriptor(
                \Webauthn\PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                base64_decode($credential->getPublicKeyCredentialId())
            );
        }

        // Create criteria using arguments (Immutable object pattern)
        $authenticatorSelectionCriteria = AuthenticatorSelectionCriteria::create(
            null, // Authenticator Attachment (null = no preference)
            AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED
        );

        $options = $factory->create(
            'default',
            $userEntity,
            $excludeCredentials,
            $authenticatorSelectionCriteria
        );

        $request->getSession()->set('webauthn_creation_options', $options);

        return new JsonResponse($this->serializer->serialize($options, 'json'), 200, [], true);
    }

    #[Route('/register/verify', name: 'app_passkey_register_verify', methods: ['POST'])]
    public function registerVerify(
        Request $request,
        PublicKeyCredentialLoader $loader,
        AuthenticatorAttestationResponseValidator $validator,
        PublicCredentialRepository $repository,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Not authenticated'], 401);
        }

        $options = $request->getSession()->get('webauthn_creation_options');
        if (!$options instanceof PublicKeyCredentialCreationOptions) {
            return new JsonResponse(['error' => 'No creation options found in session'], 400);
        }

        try {
            $content = $request->getContent();
            $publicKeyCredential = $loader->load($content);
            $response = $publicKeyCredential->getResponse();

            if (!$response instanceof AuthenticatorAttestationResponse) {
                return new JsonResponse(['error' => 'Invalid response type'], 400);
            }

            $credentialSource = $validator->check($response, $options, $request->getHost());
            $repository->saveCredentialSource($credentialSource);

            return new JsonResponse(['success' => true]);
        } catch (\Throwable $e) {
            // Log via standard logger for reliability
            $this->container->get('logger')->error('Passkey Verify Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return new JsonResponse(['error' => 'Server Error: ' . $e->getMessage()], 400);
        }
    }

    #[Route('/delete/{id}', name: 'app_passkey_delete', methods: ['POST'])]
    public function delete(int $id, EntityManagerInterface $em, PublicCredentialRepository $repo): Response
    {
        $credential = $repo->find($id);
        if (!$credential || $credential->getUser() !== $this->getUser()) {
            throw $this->createNotFoundException('Credential not found');
        }

        $em->remove($credential);
        $em->flush();

        $this->addFlash('success', 'Device removed successfully.');
        return $this->redirectToRoute('app_user_settings');
    }
}
