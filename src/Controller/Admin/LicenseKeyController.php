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

use App\Entity\Product;
use App\Entity\User;
use App\Service\JwtService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/license-keys')]
#[IsGranted('ROLE_ADMIN')]
class LicenseKeyController extends AbstractController
{
    #[Route('/', name: 'app_admin_license_keys', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $em, JwtService $jwt): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_generate_license', $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid security token.');
                return $this->redirectToRoute('app_admin_license_keys');
            }
        }
        // Only show products marked as requiring a license
        $products = $em->getRepository(Product::class)->findBy(['requiresLicense' => true]);
        $users = $em->getRepository(User::class)->findAll();
        
        $generatedKey = null;
        $debugData = null;

        if ($request->isMethod('POST')) {
            $productId = $request->request->get('product_id');
            $userId = $request->request->get('user_id');
            $duration = (int) $request->request->get('duration', 365);
            
            $product = $em->getRepository(Product::class)->find($productId);
            $user = $em->getRepository(User::class)->find($userId);

            if ($product && $user) {
                // 1. Generate the JWT Token (based on the selected Product)
                $payload = [
                    'product' => $product->getTitle(),
                    'owner' => $user->getUserIdentifier(),
                    'owner_id' => $user->getId(),
                    'type' => 'Full-Stack Licensing',
                    'v' => '1.0'
                ];
                
                $generatedKey = $jwt->generateToken($payload, $duration * 86400);
                $debugData = $payload;

                // 2. Persist to Database 
                $license = $em->getRepository(\App\Entity\License::class)->findOneBy(['slug' => 'standard-license']) 
                           ?? $em->getRepository(\App\Entity\License::class)->findOneBy([]) 
                           ?? null;

                if ($license) {
                    $userLicense = new \App\Entity\UserLicense();
                    $userLicense->setUser($user);
                    $userLicense->setLicense($license); // The legal text
                    $userLicense->setAssignedProduct($product); // The PHYSICAL product link!
                    $userLicense->setSignedAt(new \DateTime());
                    $userLicense->setIpAddress($request->getClientIp());
                    $userLicense->setSignatureType('PRODUCT_LICENSE: ' . $product->getTitle());
                    $userLicense->setLicenseKey($generatedKey);
                    
                    $em->persist($userLicense);
                    $em->flush();
                }
                
                $this->addFlash('success', 'License key for ' . $product->getTitle() . ' generated and assigned successfully!');
            } else {
                $this->addFlash('error', 'Select a product that requires a license.');
            }
        }

        return $this->render('admin/license_keys/index.html.twig', [
            'products' => $products,
            'users' => $users,
            'generated_key' => $generatedKey,
            'debug_data' => $debugData
        ]);
    }
}
