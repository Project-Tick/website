<?php

namespace App\Service;

use App\Entity\SpdxLicense;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class LicenseSyncService
{
    private const SPDX_URL = 'https://spdx.org/licenses/licenses.json';
    private const SPDX_DETAILS_BASE_URL = 'https://spdx.org/licenses/';

    public function __construct(
        private EntityManagerInterface $em,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger
    ) {}

    public function syncFromSpdx(bool $fetchContent = false): array
    {
        $response = $this->httpClient->request('GET', self::SPDX_URL);
        $data = $response->toArray();
        $licenses = $data['licenses'] ?? [];

        $stats = ['updated' => 0, 'created' => 0, 'content_fetched' => 0];

        foreach ($licenses as $item) {
            $identifier = $item['licenseId'];
            $name = $item['name'];
            $isOsi = $item['isOsiApproved'] ?? false;
            $seeAlso = $item['seeAlso'][0] ?? null;

            $license = $this->em->getRepository(SpdxLicense::class)->findOneBy(['identifier' => $identifier]);
            
            if (!$license) {
                $license = new SpdxLicense();
                $license->setIdentifier($identifier);
                $stats['created']++;
            } else {
                $stats['updated']++;
            }

            $license->setName($name);
            $license->setIsOsiApproved($isOsi);
            $license->setSeeAlso($seeAlso);

            if ($fetchContent && !$license->getContent()) {
                try {
                    $this->fetchLicenseContent($license);
                    $stats['content_fetched']++;
                } catch (\Exception $e) {
                    $this->logger->error("Failed to fetch content for $identifier: " . $e->getMessage());
                }
            }

            $this->em->persist($license);
            
            // Periodically flush to avoid memory issues and see progress
            if (($stats['created'] + $stats['updated']) % 50 === 0) {
                $this->em->flush();
            }
        }

        $this->em->flush();

        return $stats;
    }

    public function fetchLicenseContent(SpdxLicense $license): void
    {
        $url = self::SPDX_DETAILS_BASE_URL . $license->getIdentifier() . '.json';
        $response = $this->httpClient->request('GET', $url);
        
        if ($response->getStatusCode() === 200) {
            $data = $response->toArray();
            if (isset($data['licenseText'])) {
                $license->setContent($data['licenseText']);
            }
        }
    }
}
