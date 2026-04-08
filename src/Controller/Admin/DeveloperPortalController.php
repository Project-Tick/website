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

use App\Entity\DeveloperPortalEntry;
use App\Form\DeveloperPortalEntryType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/developer-portal')]
#[IsGranted('ROLE_ADMIN')]
class DeveloperPortalController extends AbstractController
{
    #[Route('/', name: 'app_admin_developer_portal')]
    public function index(EntityManagerInterface $em): Response
    {
        $entries = $em->getRepository(DeveloperPortalEntry::class)->findBy([], ['displayOrder' => 'ASC']);
        return $this->render('admin/developer_portal/index.html.twig', [
            'entries' => $entries
        ]);
    }

    #[Route('/new', name: 'app_admin_developer_portal_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $entry = new DeveloperPortalEntry();
        
        $form = $this->createForm(DeveloperPortalEntryType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($entry);
            $em->flush();

            return $this->redirectToRoute('app_admin_developer_portal');
        }

        return $this->render('admin/developer_portal/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_developer_portal_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, DeveloperPortalEntry $entry, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(DeveloperPortalEntryType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('app_admin_developer_portal');
        }

        return $this->render('admin/developer_portal/edit.html.twig', [
            'entry' => $entry,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_developer_portal_delete', methods: ['POST'])]
    public function delete(Request $request, DeveloperPortalEntry $entry, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$entry->getId(), $request->request->get('_token'))) {
            $em->remove($entry);
            $em->flush();
        }

        return $this->redirectToRoute('app_admin_developer_portal');
    }
}
