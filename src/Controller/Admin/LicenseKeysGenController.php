<?php

namespace App\Controller\Admin;

use App\Service\ModuleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/license-keys')]
class LicenseKeysGenController extends AbstractController
{
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    #[Route('', name: 'app_admin_license_keys')]
    public function index(Request $request, ModuleService $moduleService): Response
    {
        $generatedKey = null;
        $error = null;

        if ($request->isMethod('POST')) {
            $type = $request->request->get('type');
            $year = $request->request->get('year');
            $tier = $request->request->get('tier');
            $domain = $request->request->get('domain');
            
            if (!$type || !$year || !$tier || !$domain) {
                $error = "All fields are required.";
            } else {
                // Remove http/https and trailing slash if user entered a full URL
                $cleanDomain = parse_url($domain, PHP_URL_HOST) ?? $domain;
                $cleanDomain = strtolower(trim($cleanDomain, '/ '));
                
                $payload = sprintf("PT-INTERNAL-%s-%s-%s-%s", strtoupper($type), $year, strtoupper($tier), $cleanDomain);
                
                $privateKeyPath = $this->projectDir . '/config/jwt/projt_private.pem';
                
                if (!file_exists($privateKeyPath)) {
                    $error = "Private key not found on server! Cannot generate new licenses.";
                } else {
                    $privateKey = file_get_contents($privateKeyPath);
                    if (openssl_sign($payload, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                        $signatureBase64 = base64_encode($signature);
                        $generatedKey = $payload . '.' . $signatureBase64;
                    } else {
                        $error = "Failed to sign the license key.";
                    }
                }
            }
        }

        return $this->render('admin/license_keys/index.html.twig', [
            'generated_key' => $generatedKey,
            'error' => $error,
            'current_info' => $moduleService->getLicenseInfo()
        ]);
    }
}
