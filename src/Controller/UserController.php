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

use App\Entity\License;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use App\Repository\SiteSettingRepository;
use App\Service\KeycloakBrokerService;
use Symfony\Component\Uid\Uuid;

#[Route('/user')]
#[IsGranted('ROLE_USER')]
class UserController extends AbstractController
{
    #[Route('/dashboard', name: 'app_user_dashboard')]
    public function index(EntityManagerInterface $em, KeycloakBrokerService $brokerService): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $gravatarHash = md5(strtolower(trim($user->getEmail())));

        $linkedProviders = [];
        if ($user->getKeycloakId()) {
            $linked = $brokerService->getLinkedProviders($user->getKeycloakId());
            foreach ($linked as $entry) {
                $linkedProviders[$entry['identityProvider'] ?? ''] = $entry['userName'] ?? '';
            }
        }

        $activities = [];

        // 1. Real License Signatures
        foreach ($user->getUserLicenses() as $lic) {
            $activities[] = [
                'title' => 'Signed License: ' . $lic->getLicense()->getName(),
                'date' => $lic->getSignedAt(),
                'type' => 'license'
            ];
        }


        // 3. Real Roadmap Interest
        $roadmapItems = $em->getRepository(\App\Entity\RoadmapItem::class)->findAll();
        foreach ($roadmapItems as $item) {
            if ($item->getVotes()->contains($user)) {
                $activities[] = [
                    'title' => 'Upvoted Roadmap: ' . $item->getTitle(),
                    'date' => $item->getCreatedAt(), 
                    'type' => 'roadmap'
                ];
            }
        }

        // Sort by date DESC
        usort($activities, function($a, $b) {
            return $b['date'] <=> $a['date'];
        });

        // Limit to top 5
        $activities = array_slice($activities, 0, 5);


        return $this->render('user/dashboard.html.twig', [
            'user' => $user,
            'gravatarHash' => $gravatarHash,
            'activities' => $activities,
            'tick_token' => $user->getTickApiToken(),
            'linkedProviders' => $linkedProviders,
        ]);
    }

    #[Route('/licenses', name: 'app_user_licenses', methods: ['GET'])]
    public function myLicenses(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $licenses = $user->getUserLicenses();
        

        return $this->render('user/licenses.html.twig', [
            'user_licenses' => $licenses
        ]);
    }


    #[Route('/settings', name: 'app_user_settings')]
    public function settings(Request $request, EntityManagerInterface $em, SluggerInterface $slugger, KeycloakBrokerService $brokerService): Response
    {
        $user = $this->getUser();

        $linkedProviders = [];
        if ($user->getKeycloakId()) {
            $linked = $brokerService->getLinkedProviders($user->getKeycloakId());
            foreach ($linked as $entry) {
                $linkedProviders[$entry['identityProvider'] ?? ''] = $entry['userName'] ?? '';
            }
        }
        
        // Profile Form
        $formProfile = $this->createForm(\App\Form\UserProfileType::class, $user);
        $formProfile->handleRequest($request);

        if ($formProfile->isSubmitted() && $formProfile->isValid()) {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $avatarFile */
            $avatarFile = $formProfile->get('avatar')->getData();

            if ($avatarFile) {
                $originalFilename = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$avatarFile->guessExtension();

                try {
                    $avatarFile->move(
                        $this->getParameter('kernel.project_dir').'/public/uploads/avatars',
                        $newFilename
                    );
                    $user->setAvatar($newFilename);
                } catch (FileException $e) {
                     $this->addFlash('error', 'Error uploading avatar: '.$e->getMessage());
                }
            }

            $em->flush();
            $this->addFlash('success', 'Profile updated successfully.');
            return $this->redirectToRoute('app_user_settings');
        }
        
        return $this->render('user/settings.html.twig', [
            'formProfile' => $formProfile,
            'user' => $user,
            'linkedProviders' => $linkedProviders,
        ]);
    }

    #[Route('/settings/email-change', name: 'app_user_email_change_request', methods: ['POST'])]
    public function requestEmailChange(Request $request, EntityManagerInterface $em, MailerInterface $mailer, UserPasswordHasherInterface $passwordHasher): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        
        $newEmail = $request->request->get('email');
        $password = $request->request->get('current_password');

        if (!$this->isCsrfTokenValid('request_email_change', $request->request->get('_token'))) {
             $this->addFlash('error', 'Invalid security token.');
             return $this->redirectToRoute('app_user_settings');
        }

        if (!$passwordHasher->isPasswordValid($user, $password)) {
            $this->addFlash('error', 'Invalid password.');
            return $this->redirectToRoute('app_user_settings');
        }

        if ($newEmail === $user->getEmail()) {
            $this->addFlash('error', 'This is already your email address.');
            return $this->redirectToRoute('app_user_settings');
        }

        $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $newEmail]);
        if ($existingUser) {
            $this->addFlash('error', 'This email address is already in use.');
            return $this->redirectToRoute('app_user_settings');
        }

        $token = Uuid::v4()->toBase32();
        $user->setEmailChangeToken($token);
        $user->setNewEmailPending($newEmail);
        $em->flush();

        $confirmUrl = $this->generateUrl('app_user_email_change_confirm', ['token' => $token], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);

        $emailMessage = (new Email())
            ->from('noreply@projecttick.org')
            ->to($newEmail)
            ->subject('Confirm your email change')
            ->html($this->renderView('emails/email_change_confirm.html.twig', [
                'confirmUrl' => $confirmUrl,
                'user' => $user
            ]));

        $mailer->send($emailMessage);

        $this->addFlash('success', 'A confirmation email has been sent to ' . $newEmail . '. Please click the link in that email to complete the change.');
        return $this->redirectToRoute('app_user_settings');
    }

    #[Route('/settings/email-confirm/{token}', name: 'app_user_email_change_confirm', methods: ['GET'])]
    public function confirmEmailChange(string $token, EntityManagerInterface $em): Response
    {
        $user = $em->getRepository(User::class)->findOneBy(['emailChangeToken' => $token]);

        if (!$user || !$user->getNewEmailPending()) {
            $this->addFlash('error', 'Invalid or expired confirmation token.');
            return $this->redirectToRoute('app_user_settings');
        }

        $user->setEmail($user->getNewEmailPending());
        $user->setEmailChangeToken(null);
        $user->setNewEmailPending(null);
        $em->flush();

        $this->addFlash('success', 'Your email address has been updated successfully.');
        return $this->redirectToRoute('app_user_settings');
    }


    #[Route('/delete', name: 'app_user_delete', methods: ['POST'])]
    public function deleteAccount(Request $request, EntityManagerInterface $em, TokenStorageInterface $tokenStorage): Response
    {
        if (!$this->isCsrfTokenValid('delete_account', $request->request->get('_token'))) {
             $this->addFlash('error', 'Invalid token.');
             return $this->redirectToRoute('app_user_settings');
        }
        
        $user = $this->getUser();
        $em->remove($user);
        $em->flush();
        
        $tokenStorage->setToken(null);
        $request->getSession()->invalidate();
        
        $this->addFlash('info', 'Your account has been deleted.');
        return $this->redirectToRoute('app_home');
    }
}
