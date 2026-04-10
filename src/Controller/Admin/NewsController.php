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

use App\Entity\News;
use App\Form\NewsType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/news')]
#[IsGranted('ROLE_ADMIN')]
class NewsController extends AbstractController
{
    #[Route('/', name: 'app_admin_news', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $news = $em->getRepository(News::class)->findBy([], ['date' => 'DESC']);
        return $this->render('admin/news/index.html.twig', ['news' => $news]);
    }

    #[Route('/new', name: 'app_admin_news_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $news = new News();
        $news->setDate(new \DateTime());
        
        $form = $this->createForm(NewsType::class, $news);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleMetadataSync($news, $form);
            
            if (!$news->getSlug()) {
                $news->setSlug(strtolower($slugger->slug($news->getTitle())));
            }

            // Set current user as author
            $news->setAuthor($this->getUser());

            $em->persist($news);

            $em->flush();

            return $this->redirectToRoute('app_admin_news');
        }

        return $this->render('admin/news/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_news_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, News $news, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        // Pre-fill unmapped Sparkle fields from metadata
        $metadata = $news->getMetadata() ?? [];
        $form = $this->createForm(NewsType::class, $news);
        
        // Manually set data for unmapped fields
        $fields = ['release_version', 'release_tag', 'minimum_macos_version', 'macos_file_extension', 'macos_signature'];
        foreach ($fields as $field) {
            if (isset($metadata[$field]) && $form->has($field)) {
                $form->get($field)->setData($metadata[$field]);
            }
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleMetadataSync($news, $form);

            if (!$news->getSlug()) {
                $news->setSlug(strtolower($slugger->slug($news->getTitle())));
            }

            $em->flush();

            return $this->redirectToRoute('app_admin_news');
        }

        return $this->render('admin/news/edit.html.twig', [
            'news' => $news,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_news_delete', methods: ['POST'])]
    public function delete(Request $request, News $news, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$news->getId(), $request->request->get('_token'))) {
            $em->remove($news);
            $em->flush();
        }

        return $this->redirectToRoute('app_admin_news');
    }

    /**
     * Enhanced sync between unmapped Sparkle fields and the metadata JSON column.
     * Also extracts version info from title as a fallback.
     */
    private function handleMetadataSync(News $news, $form): void
    {
        $metadata = $news->getMetadata() ?? [];
        
        // Sync Sparkle fields
        $sparkleFields = ['release_version', 'release_tag', 'minimum_macos_version', 'macos_file_extension', 'macos_signature'];
        foreach ($sparkleFields as $field) {
            if ($form->has($field)) {
                $value = $form->get($field)->getData();
                if ($value) {
                    $metadata[$field] = $value;
                }
            }
        }

        // Auto-extract version from title if missing in metadata
        if (empty($metadata['release_version'])) {
            if (preg_match('/Update\s+([\d]+\.[\d]+\.[\d]+(?:-\d+)?)/i', $news->getTitle(), $m)) {
                $metadata['release_version'] = $m[1];
            }
        }

        // Ensure tags exist for release news
        if ($news->getProduct() && !empty($metadata['release_version'])) {
            if (empty($metadata['tags'])) {
                $metadata['tags'] = ['release'];
            } elseif (!in_array('release', $metadata['tags'])) {
                $metadata['tags'][] = 'release';
            }
        }

        $news->setMetadata($metadata);
    }
}
