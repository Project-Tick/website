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

namespace App\Module\Community;

use App\Entity\DiscussionCategory;
use App\Entity\DiscussionThread;
use App\Entity\DiscussionComment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/community')]
class DiscussionController extends AbstractController
{
    #[Route('', name: 'app_community')]
    public function index(EntityManagerInterface $em): Response
    {
        $categories = $em->getRepository(DiscussionCategory::class)->findAll();
        $recentThreads = $em->getRepository(DiscussionThread::class)->findBy([], ['createdAt' => 'DESC'], 5);

        return $this->render('community/index.html.twig', [
            'categories' => $categories,
            'recentThreads' => $recentThreads
        ]);
    }

    #[Route('/category/{id}', name: 'app_community_category')]
    public function category(DiscussionCategory $category): Response
    {
        return $this->render('community/category.html.twig', [
            'category' => $category
        ]);
    }

    #[Route('/thread/{id}', name: 'app_community_thread')]
    public function thread(DiscussionThread $thread): Response
    {
        return $this->render('community/thread.html.twig', [
            'thread' => $thread
        ]);
    }

    #[Route('/thread/new/{categoryId}', name: 'app_community_thread_new', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function newThread(int $categoryId, Request $request, EntityManagerInterface $em): Response
    {
        $category = $em->getRepository(DiscussionCategory::class)->find($categoryId);
        if (!$category) throw $this->createNotFoundException();

        if ($request->isMethod('POST')) {
            $thread = new DiscussionThread();
            $thread->setTitle($request->request->get('title'));
            $thread->setContent($request->request->get('content'));
            $thread->setCategory($category);
            $thread->setAuthor($this->getUser());

            $em->persist($thread);
            $em->flush();

            return $this->redirectToRoute('app_community_thread', ['id' => $thread->getId()]);
        }

        return $this->render('community/new_thread.html.twig', ['category' => $category]);
    }

    #[Route('/thread/{id}/comment', name: 'app_community_comment_new', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function newComment(DiscussionThread $thread, Request $request, EntityManagerInterface $em): Response
    {
        $comment = new DiscussionComment();
        $comment->setContent($request->request->get('content'));
        $comment->setThread($thread);
        $comment->setAuthor($this->getUser());

        $em->persist($comment);
        $em->flush();

        return $this->redirectToRoute('app_community_thread', ['id' => $thread->getId()]);
    }
}
