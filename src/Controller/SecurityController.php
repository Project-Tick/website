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

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

use Symfony\Component\HttpFoundation\Request;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // if ($this->getUser()) {
        //     return $this->redirectToRoute('target_path');
        // }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route(path: '/successful-login', name: 'app_successful_login')]
    public function successfulLogin(): Response
    {
        return $this->render('security/successful_login.html.twig');
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/forgot-password', name: 'app_forgot_password_request')]
    public function forgotPasswordRequest(Request $request, EntityManagerInterface $em, MailerInterface $mailer, \Symfony\Component\RateLimiter\RateLimiterFactory $passwordResetLimiter): Response
    {
        if ($request->isMethod('POST')) {
            $limiter = $passwordResetLimiter->create($request->getClientIp());
            if (false === $limiter->consume(1)->isAccepted()) {
                $this->addFlash('error', 'Too many requests. Please try again in 15 minutes.');
                return $this->redirectToRoute('app_forgot_password_request');
            }

            if (!$this->isCsrfTokenValid('forgot_password', $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid security token.');
                return $this->redirectToRoute('app_forgot_password_request');
            }
            $email = $request->request->get('email');
            $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

            if ($user) {
                $token = Uuid::v4()->toBase32();
                $user->setResetPasswordToken($token);
                $user->setResetPasswordExpiresAt(new \DateTime('+1 hour'));
                $em->flush();

                $resetUrl = $this->generateUrl('app_reset_password', ['token' => $token], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);

                $emailMessage = (new Email())
                    ->from('noreply@projecttick.org')
                    ->to($user->getEmail())
                    ->subject('Password Reset Request')
                    ->html($this->renderView('emails/password_reset.html.twig', [
                        'resetUrl' => $resetUrl,
                        'user' => $user
                    ]));

                $mailer->send($emailMessage);
            }

            $this->addFlash('success', 'If an account exists for that email, a password reset link has been sent.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/forgot_password_request.html.twig');
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password')]
    public function resetPassword(string $token, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = $em->getRepository(User::class)->findOneBy(['resetPasswordToken' => $token]);

        if (!$user || $user->getResetPasswordExpiresAt() < new \DateTime()) {
            $this->addFlash('error', 'Invalid or expired reset token.');
            return $this->redirectToRoute('app_forgot_password_request');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('reset_password', $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid security token.');
                return $this->redirectToRoute('app_reset_password', ['token' => $token]);
            }
            $password = $request->request->get('password');
            $confirm = $request->request->get('confirm_password');

            if ($password !== $confirm) {
                $this->addFlash('error', 'Passwords do not match.');
            } else {
                $user->setPassword($passwordHasher->hashPassword($user, $password));
                $user->setResetPasswordToken(null);
                $user->setResetPasswordExpiresAt(null);
                $em->flush();

                $this->addFlash('success', 'Your password has been reset successfully.');
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/reset_password.html.twig', ['token' => $token]);
    }
}
