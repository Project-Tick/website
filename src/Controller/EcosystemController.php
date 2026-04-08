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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EcosystemController extends AbstractController
{
    #[Route('/ecosystem', name: 'app_ecosystem')]
    public function index(\App\Repository\EcosystemNodeRepository $nodeRepo): Response
    {
        return $this->render('main/ecosystem.html.twig', [
            'nodes' => $nodeRepo->findAll()
        ]);
    }

    #[Route('/ecosystem/developers', name: 'app_ecosystem_developers')]
    public function developers(\Doctrine\ORM\EntityManagerInterface $em): Response
    {
        $entries = $em->getRepository(\App\Entity\DeveloperPortalEntry::class)->findBy([], ['displayOrder' => 'ASC']);
        
        return $this->render('main/ecosystem_developers.html.twig', [
            'entries' => $entries
        ]);
    }

    #[Route('/ecosystem/showcase', name: 'app_ecosystem_showcase')]
    public function showcase(\App\Repository\AppShowcaseRepository $repo): Response
    {
        return $this->render('main/showcase.html.twig', [
            'apps' => $repo->findBy([], ['isFeatured' => 'DESC', 'id' => 'DESC'])
        ]);
    }
}
