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

use App\Entity\HandbookEntry;
use App\Form\HandbookEntryType;
use App\Entity\License;
use App\Entity\LicenseCategory;
use App\Entity\News;
use App\Entity\NewsCategory;
use App\Entity\User;
use App\Form\LicenseType;
use App\Form\LicenseCategoryType;
use App\Form\NewsCategoryType;
use App\Form\NewsType;
use App\Form\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_admin_dashboard')]
    public function index(EntityManagerInterface $em, \App\Service\ModuleService $modules): Response
    {
        $visitRepo = $em->getRepository(\App\Entity\Visit::class);
        $totalVisits = $visitRepo->count([]);
        
        $yesterday = new \DateTimeImmutable('-24 hours');
        $recentVisitsQuery = $em->createQuery('SELECT COUNT(v.id) FROM App\Entity\Visit v WHERE v.visitedAt > :y')
            ->setParameter('y', $yesterday);
        $recentVisits = $recentVisitsQuery->getSingleScalarResult();

        // Stats for Charts/Widgets
        $osStats = $em->createQuery('SELECT v.os, COUNT(v.id) as count FROM App\Entity\Visit v GROUP BY v.os')->getResult();
        $browserStats = $em->createQuery('SELECT v.browser, COUNT(v.id) as count FROM App\Entity\Visit v GROUP BY v.browser')->getResult();

        return $this->render('admin/dashboard/index.html.twig', [
            'license_count' => $em->getRepository(License::class)->count([]),
            'handbook_count' => $em->getRepository(HandbookEntry::class)->count([]),
            'news_count' => $em->getRepository(News::class)->count([]),
            'community_count' => $em->getRepository(\App\Entity\DiscussionThread::class)->count([]),
            'feedback_helpful' => $em->getRepository(\App\Entity\HandbookFeedback::class)->count(['helpful' => true]),
            'feedback_total' => $em->getRepository(\App\Entity\HandbookFeedback::class)->count([]),
            'total_visits' => $totalVisits,
            'os_stats' => $osStats,
            'browser_stats' => $browserStats,
            'recent_visits' => $recentVisits,
            'version' => $modules->getVersion(),
            'is_authorized' => $modules->isAuthorized(),
            'license_info' => $modules->getLicenseInfo(),
            'modules' => [
                'monitor' => $modules->isMonitorEnabled(),
                'community' => $modules->isCommunityEnabled(),
            ]
        ]);
    }

    // --- Roadmap Management ---

    #[Route('/roadmap', name: 'app_admin_roadmap')]
    public function listRoadmap(EntityManagerInterface $em): Response
    {
        $items = $em->getRepository(\App\Entity\RoadmapItem::class)->findBy([], ['priority' => 'DESC']);
        return $this->render('admin/roadmap/index.html.twig', ['items' => $items]);
    }

    #[Route('/roadmap/new', name: 'app_admin_roadmap_new', methods: ['GET', 'POST'])]
    public function newRoadmap(Request $request, EntityManagerInterface $em): Response
    {
        $item = new \App\Entity\RoadmapItem();
        if ($request->isMethod('POST')) {
            $item->setTitle($request->request->get('title'));
            $item->setStatus($request->request->get('status'));
            $item->setPriority((int)$request->request->get('priority'));
            $item->setDescription($request->request->get('description'));
            
            $em->persist($item);
            $em->flush();
            return $this->redirectToRoute('app_admin_roadmap');
        }

        return $this->render('admin/roadmap/new.html.twig', ['item' => $item]);
    }

    #[Route('/roadmap/{id}/edit', name: 'app_admin_roadmap_edit', methods: ['GET', 'POST'])]
    public function editRoadmap(Request $request, \App\Entity\RoadmapItem $item, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $item->setTitle($request->request->get('title'));
            $item->setStatus($request->request->get('status'));
            $item->setPriority((int)$request->request->get('priority'));
            $item->setDescription($request->request->get('description'));
            
            $em->flush();
            return $this->redirectToRoute('app_admin_roadmap');
        }

        return $this->render('admin/roadmap/edit.html.twig', ['item' => $item]);
    }

    #[Route('/roadmap/{id}/delete', name: 'app_admin_roadmap_delete', methods: ['POST'])]
    public function deleteRoadmap(Request $request, \App\Entity\RoadmapItem $item, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$item->getId(), $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
        }
        return $this->redirectToRoute('app_admin_roadmap');
    }



    #[Route('/licenses', name: 'app_admin_licenses')]
    public function listLicenses(EntityManagerInterface $em): Response
    {
        $licenses = $em->getRepository(License::class)->findAll();
        return $this->render('admin/licenses/index.html.twig', ['licenses' => $licenses]);
    }

    #[Route('/licenses/new', name: 'app_admin_licenses_new', methods: ['GET', 'POST'])]
    public function newLicense(Request $request, EntityManagerInterface $em): Response
    {
        $license = new License();
        $form = $this->createForm(LicenseType::class, $license);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($license);
            $em->flush();

            return $this->redirectToRoute('app_admin_licenses');
        }

        return $this->render('admin/licenses/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/licenses/{id}/edit', name: 'app_admin_licenses_edit', methods: ['GET', 'POST'])]
    public function editLicense(Request $request, License $license, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(LicenseType::class, $license);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('app_admin_licenses');
        }

        return $this->render('admin/licenses/edit.html.twig', [
            'license' => $license,
            'form' => $form->createView(),
        ]);
    }



    #[Route('/licenses/{id}/delete', name: 'app_admin_licenses_delete', methods: ['POST'])]
    public function deleteLicense(Request $request, License $license, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$license->getId(), $request->request->get('_token'))) {
            $em->remove($license);
            $em->flush();
        }

        return $this->redirectToRoute('app_admin_licenses');
    }

    #[Route('/handbook', name: 'app_admin_handbook')]
    public function listHandbook(EntityManagerInterface $em): Response
    {
        $entries = $em->getRepository(HandbookEntry::class)->findAll();
        return $this->render('admin/handbook/index.html.twig', ['entries' => $entries]);
    }

    #[Route('/handbook/new', name: 'app_admin_handbook_new', methods: ['GET', 'POST'])]
    public function newHandbook(Request $request, EntityManagerInterface $em): Response
    {
        $entry = new HandbookEntry();
        $form = $this->createForm(HandbookEntryType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($entry);
            $em->flush();

            return $this->redirectToRoute('app_admin_handbook');
        }

        return $this->render('admin/handbook/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/handbook/{id}/edit', name: 'app_admin_handbook_edit', methods: ['GET', 'POST'])]
    public function editHandbook(Request $request, HandbookEntry $entry, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(HandbookEntryType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('app_admin_handbook');
        }

        return $this->render('admin/handbook/edit.html.twig', [
            'entry' => $entry,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/handbook/{id}/delete', name: 'app_admin_handbook_delete', methods: ['POST'])]
    public function deleteHandbook(Request $request, HandbookEntry $entry, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$entry->getId(), $request->request->get('_token'))) {
            $em->remove($entry);
            $em->flush();
        }

        return $this->redirectToRoute('app_admin_handbook');
    }

    #[Route('/register', name: 'app_admin_register', methods: ['GET', 'POST'])]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            // Manual CSRF Check
            if (!$this->isCsrfTokenValid('admin_register', $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid CSRF token.');
                return $this->redirectToRoute('app_admin_register');
            }

            $email = $request->request->get('email');
            
            // Check if user already exists
            $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existingUser) {
                $this->addFlash('error', 'User with this email already exists.');
                return $this->redirectToRoute('app_admin_register');
            }

            $user = new User();
            $user->setEmail($email);
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $request->request->get('password')
                )
            );
            $user->setRoles(['ROLE_USER']);

            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/register.html.twig');
    }

    #[Route('/categories', name: 'app_admin_categories')]
    public function listCategories(EntityManagerInterface $em): Response
    {
        $categories = $em->getRepository(NewsCategory::class)->findAll();
        return $this->render('admin/categories/index.html.twig', ['categories' => $categories]);
    }

    #[Route('/categories/new', name: 'app_admin_categories_new', methods: ['GET', 'POST'])]
    public function newCategory(Request $request, EntityManagerInterface $em): Response
    {
        $category = new NewsCategory();
        $form = $this->createForm(NewsCategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($category);
            $em->flush();

            return $this->redirectToRoute('app_admin_categories');
        }

        return $this->render('admin/categories/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/categories/{id}/edit', name: 'app_admin_categories_edit', methods: ['GET', 'POST'])]
    public function editCategory(Request $request, NewsCategory $category, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(NewsCategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('app_admin_categories');
        }

        return $this->render('admin/categories/edit.html.twig', [
            'category' => $category,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/categories/{id}/delete', name: 'app_admin_categories_delete', methods: ['POST'])]
    public function deleteCategory(Request $request, NewsCategory $category, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$category->getId(), $request->request->get('_token'))) {
            $em->remove($category);
            $em->flush();
        }

        return $this->redirectToRoute('app_admin_categories');
    }

    #[Route('/users', name: 'app_admin_users')]
    public function listUsers(EntityManagerInterface $em): Response
    {
        $users = $em->getRepository(User::class)->findAll();
        return $this->render('admin/users/index.html.twig', ['users' => $users]);
    }

    #[Route('/users/{id}/edit', name: 'app_admin_users_edit', methods: ['GET', 'POST'])]
    public function editUser(Request $request, User $user, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/users/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/users/{id}/delete', name: 'app_admin_users_delete', methods: ['POST'])]
    public function deleteUser(Request $request, User $user, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $em->remove($user);
            $em->flush();
        }

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/license-categories', name: 'app_admin_license_categories')]
    public function listLicenseCategories(EntityManagerInterface $em): Response
    {
        $categories = $em->getRepository(LicenseCategory::class)->findBy([], ['displayOrder' => 'ASC']);
        return $this->render('admin/license_categories/index.html.twig', ['categories' => $categories]);
    }

    #[Route('/license-categories/new', name: 'app_admin_license_categories_new', methods: ['GET', 'POST'])]
    public function newLicenseCategory(Request $request, EntityManagerInterface $em): Response
    {
        $category = new LicenseCategory();
        $form = $this->createForm(LicenseCategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($category);
            $em->flush();
            return $this->redirectToRoute('app_admin_license_categories');
        }

        return $this->render('admin/license_categories/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/license-categories/{id}/edit', name: 'app_admin_license_categories_edit', methods: ['GET', 'POST'])]
    public function editLicenseCategory(Request $request, LicenseCategory $category, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(LicenseCategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return $this->redirectToRoute('app_admin_license_categories');
        }

        return $this->render('admin/license_categories/edit.html.twig', [
            'category' => $category,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/license-categories/{id}/delete', name: 'app_admin_license_categories_delete', methods: ['POST'])]
    public function deleteLicenseCategory(Request $request, LicenseCategory $category, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$category->getId(), $request->request->get('_token'))) {
            $em->remove($category);
            $em->flush();
        }
        return $this->redirectToRoute('app_admin_license_categories');
    }

    // --- Community Management ---

    #[Route('/community', name: 'app_admin_community')]
    public function listCommunity(EntityManagerInterface $em): Response
    {
        $categories = $em->getRepository(\App\Entity\DiscussionCategory::class)->findAll();
        $recentThreads = $em->getRepository(\App\Entity\DiscussionThread::class)->findBy([], ['createdAt' => 'DESC'], 50);
        return $this->render('admin/community/index.html.twig', [
            'categories' => $categories,
            'threads' => $recentThreads
        ]);
    }

    #[Route('/community/category/new', name: 'app_admin_community_category_new', methods: ['GET', 'POST'])]
    public function newCommunityCategory(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $cat = new \App\Entity\DiscussionCategory();
            $cat->setName($request->request->get('name'));
            $cat->setDescription($request->request->get('description'));
            $em->persist($cat);
            $em->flush();
            return $this->redirectToRoute('app_admin_community');
        }
        return $this->render('admin/community/category_new.html.twig');
    }

    #[Route('/community/thread/{id}/delete', name: 'app_admin_community_thread_delete', methods: ['POST'])]
    public function deleteThread(Request $request, \App\Entity\DiscussionThread $thread, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_thread'.$thread->getId(), $request->request->get('_token'))) {
            $em->remove($thread);
            $em->flush();
        }
        return $this->redirectToRoute('app_admin_community');
    }

    // --- Feedback Management ---

    #[Route('/feedback', name: 'app_admin_feedback')]
    public function listFeedback(EntityManagerInterface $em): Response
    {
        $feedbacks = $em->getRepository(\App\Entity\HandbookFeedback::class)->findBy([], ['createdAt' => 'DESC'], 100);
        return $this->render('admin/feedback/index.html.twig', ['feedbacks' => $feedbacks]);
    }
}
