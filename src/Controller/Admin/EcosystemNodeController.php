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

use App\Entity\EcosystemNode;
use App\Form\EcosystemNodeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/ecosystem')]
#[IsGranted('ROLE_ADMIN')]
class EcosystemNodeController extends AbstractController
{
    #[Route('/', name: 'app_admin_ecosystem')]
    public function index(EntityManagerInterface $em): Response
    {
        $nodes = $em->getRepository(EcosystemNode::class)->findAll();
        return $this->render('admin/ecosystem/index.html.twig', ['nodes' => $nodes]);
    }

    #[Route('/new', name: 'app_admin_ecosystem_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $node = new EcosystemNode();
        $form = $this->createForm(EcosystemNodeType::class, $node);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($node);
            $em->flush();
            return $this->redirectToRoute('app_admin_ecosystem');
        }

        return $this->render('admin/ecosystem/new.html.twig', [
            'node' => $node,
            'form' => $form->createView()
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_ecosystem_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EcosystemNode $node, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(EcosystemNodeType::class, $node);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return $this->redirectToRoute('app_admin_ecosystem');
        }

        return $this->render('admin/ecosystem/edit.html.twig', [
            'node' => $node,
            'form' => $form->createView()
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_ecosystem_delete', methods: ['POST'])]
    public function delete(Request $request, EcosystemNode $node, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$node->getId(), $request->request->get('_token'))) {
            $em->remove($node);
            $em->flush();
        }
        return $this->redirectToRoute('app_admin_ecosystem');
    }
}
