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

namespace App\Controller\Admin;

use App\Entity\AppShowcase;
use App\Form\AppShowcaseType;
use App\Repository\AppShowcaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/showcase')]
#[IsGranted('ROLE_ADMIN')]
class ShowcaseController extends AbstractController
{
    #[Route('/', name: 'app_admin_showcase')]
    public function index(AppShowcaseRepository $repo): Response
    {
        return $this->render('admin/showcase/index.html.twig', [
            'apps' => $repo->findAll()
        ]);
    }

    #[Route('/new', name: 'app_admin_showcase_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $app = new AppShowcase();
        $form = $this->createForm(AppShowcaseType::class, $app);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($app);
            $em->flush();
            return $this->redirectToRoute('app_admin_showcase');
        }

        return $this->render('admin/showcase/new.html.twig', [
            'showcaseApp' => $app,
            'form' => $form->createView()
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_showcase_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, AppShowcase $app, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(AppShowcaseType::class, $app);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return $this->redirectToRoute('app_admin_showcase');
        }

        return $this->render('admin/showcase/edit.html.twig', [
            'showcaseApp' => $app,
            'form' => $form->createView()
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_showcase_delete', methods: ['POST'])]
    public function delete(Request $request, AppShowcase $app, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$app->getId(), $request->request->get('_token'))) {
            $em->remove($app);
            $em->flush();
        }
        return $this->redirectToRoute('app_admin_showcase');
    }
}
