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

use App\Entity\SiteSetting;
use App\Form\SiteSettingType;
use App\Repository\SiteSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/settings')]
#[IsGranted('ROLE_ADMIN')]
class SiteSettingController extends AbstractController
{
    #[Route('/', name: 'app_admin_settings_index', methods: ['GET'])]
    public function index(SiteSettingRepository $siteSettingRepository): Response
    {
        return $this->render('admin/site_setting/index.html.twig', [
            'site_settings' => $siteSettingRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_admin_settings_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $siteSetting = new SiteSetting();
        $form = $this->createForm(SiteSettingType::class, $siteSetting);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($siteSetting);
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_settings_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/site_setting/new.html.twig', [
            'site_setting' => $siteSetting,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_settings_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SiteSetting $siteSetting, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SiteSettingType::class, $siteSetting);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_settings_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/site_setting/edit.html.twig', [
            'site_setting' => $siteSetting,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_settings_delete', methods: ['POST'])]
    public function delete(Request $request, SiteSetting $siteSetting, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$siteSetting->getId(), $request->request->get('_token'))) {
            $entityManager->remove($siteSetting);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_admin_settings_index', [], Response::HTTP_SEE_OTHER);
    }
}
