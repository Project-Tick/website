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

use App\Service\MinecraftAuthService;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MicrosoftController extends AbstractController
{
    /**
     * Link to this controller to start the "connect" process
     */
    #[Route('/connect/microsoft', name: 'connect_microsoft_start')]
    public function connectAction(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry
            ->getClient('microsoft')
            ->redirect(['openid', 'email', 'profile', 'XboxLive.SignIn', 'XboxLive.offline_access'], []);
    }

    /**
     * This route is just a placeholder. The authentication is handled by the
     * App\Security\MicrosoftAuthenticator
     */
    #[Route('/connect/microsoft/check', name: 'connect_microsoft_check')]
    public function connectCheckAction()
    {
        // This method will not be executed!
    }

    #[Route('/auth/minecraft/success', name: 'app_minecraft_auth_success')]
    public function success(Request $request): Response
    {
        $minecraftData = $request->getSession()->get('minecraft_auth');

        if (!$minecraftData) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('main/minecraft_auth_success.html.twig', [
            'data' => $minecraftData
        ]);
    }
}
