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

namespace App\Module\Monitor;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/monitors')]
#[IsGranted('ROLE_ADMIN')]
class MonitorAdminController extends AbstractController
{
    public function __construct(
        private UptimeService $uptimeService
    ) {}

    #[Route('', name: 'admin_monitors_index')]
    public function index(): Response
    {
        return $this->render('admin/monitors/index.html.twig', [
            'monitors' => $this->uptimeService->getMonitors()
        ]);
    }

    #[Route('/new', name: 'admin_monitors_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $data = [
                'name' => $request->get('name'),
                'url' => $request->get('url'),
                'type' => $request->get('type', 'http'),
                'interval' => (int)$request->get('interval', 60),
                'description' => $request->get('description'),
            ];

            $this->uptimeService->createMonitor($data);
            $this->addFlash('success', 'Monitor created successfully.');
            return $this->redirectToRoute('admin_monitors_index');
        }

        return $this->render('admin/monitors/new.html.twig');
    }

    #[Route('/{id}/delete', name: 'admin_monitors_delete', methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete_monitor' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_monitors_index');
        }

        $this->uptimeService->deleteMonitor($id);
        $this->addFlash('success', 'Monitor deleted.');
        return $this->redirectToRoute('admin_monitors_index');
    }
}
