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

use App\Entity\Product;
use App\Entity\UserLicense;
use App\Entity\License;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/checkout')]
#[IsGranted('ROLE_USER')]
class CheckoutController extends AbstractController
{
    #[Route('/{slug}', name: 'app_checkout')]
    public function index(string $slug, EntityManagerInterface $em): Response
    {
        $product = $em->getRepository(Product::class)->findOneBy(['slug' => $slug]);

        if (!$product || !$product->isRequiresLicense()) {
            throw $this->createNotFoundException('This product does not require a license or does not exist.');
        }

        // Check if user already has it
        $existing = $em->getRepository(UserLicense::class)->findOneBy([
            'user' => $this->getUser(),
            'assignedProduct' => $product
        ]);

        if ($existing) {
            $this->addFlash('info', 'You already own a license for ' . $product->getTitle());
            return $this->redirectToRoute('app_product_show', ['slug' => $slug]);
        }

        return $this->render('checkout/index.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{slug}/pay-test', name: 'app_checkout_pay_test', methods: ['POST'])]
    public function payTest(string $slug, Request $request, EntityManagerInterface $em, \App\Service\JwtService $jwt): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('pay_test', $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid security token.');
                return $this->redirectToRoute('app_checkout', ['slug' => $slug]);
            }
        }
        // THIS IS FOR TESTING ONLY - Bypass real payment
        $product = $em->getRepository(Product::class)->findOneBy(['slug' => $slug]);
        if (!$product) throw $this->createNotFoundException();

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $license = $em->getRepository(License::class)->findOneBy(['slug' => 'standard-license']) 
                   ?? $em->getRepository(License::class)->findOneBy([]);

        if (!$license) {
            $this->addFlash('error', 'Critical Error: Standard license template missing.');
            return $this->redirectToRoute('app_checkout', ['slug' => $slug]);
        }

        // Generate the JWT Key
        $payload = [
            'product' => $product->getTitle(),
            'owner' => $user->getUserIdentifier(),
            'owner_id' => $user->getId(),
            'type' => 'Full-Stack Licensing',
            'v' => '1.0'
        ];
        $generatedKey = $jwt->generateToken($payload, 365 * 86400);

        // Record the license
        $userLicense = new UserLicense();
        $userLicense->setUser($user);
        $userLicense->setLicense($license);
        $userLicense->setAssignedProduct($product);
        $userLicense->setSignedAt(new \DateTime());
        $userLicense->setIpAddress($request->getClientIp());
        $userLicense->setSignatureType('TEST_SIMULATION: ' . $product->getTitle());
        $userLicense->setLicenseKey($generatedKey);

        $em->persist($userLicense);
        $em->flush();

        $this->addFlash('success', '[TEST MODE] Payment simulated successfully! License issued.');
        
        return $this->redirectToRoute('app_user_licenses');
    }
}
