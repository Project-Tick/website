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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/gitlab')]
class GitlabWebhookController extends AbstractController
{
    private string $webhookSecret;
    public function __construct(string $webhookSecret)
    {
        $this->webhookSecret = $webhookSecret;
    }

    #[Route('/webhook', name: 'app_gitlab_webhook', methods: ['POST'])]
    public function handleWebhook(Request $request): Response
    {
        $token = $request->headers->get('X-Gitlab-Token');
        if ($this->webhookSecret && $token !== $this->webhookSecret) {
            return new Response('Invalid token', 403);
        }

        $payload = json_decode($request->getContent(), true);
        if (!$payload) {
            return new Response('Invalid payload', 400);
        }

        $event = $request->headers->get('X-Gitlab-Event');

        if ($event === 'Merge Request Hook') {
            $objectAttributes = $payload['object_attributes'];
            $projectId = $objectAttributes['target_project_id'];
            $mrIid = $objectAttributes['iid'];
            $sha = $objectAttributes['last_commit']['id'];
            $state = $objectAttributes['state'];

            // CLA check removed

            return new Response('Merge Request Webhook processed', 200);
        }

        return new Response('Event ignored', 200);
    }
}
