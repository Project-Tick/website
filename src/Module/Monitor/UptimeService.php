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

namespace App\Module\Monitor;

use App\Entity\Heartbeat;
use App\Entity\Monitor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class UptimeService
{
    public function __construct(
        private EntityManagerInterface $em,
        private HttpClientInterface $httpClient
    ) {}

    public function getMonitors(): array
    {
        return $this->em->getRepository(Monitor::class)->findBy([], ['weight' => 'DESC']);
    }

    public function getRecentHeartbeats(Monitor $monitor, int $limit = 50): array
    {
        return $this->em->getRepository(Heartbeat::class)->findBy(
            ['monitor' => $monitor],
            ['timestamp' => 'DESC'],
            $limit
        );
    }

    /**
     * Uptime Kuma'ya fark atan akıllı kontrol metodu
     */
    public function performCheck(Monitor $monitor): Heartbeat
    {
        $heartbeat = new Heartbeat();
        $heartbeat->setMonitor($monitor);
        
        $start = microtime(true);
        try {
            if ($monitor->getType() === 'http') {
                $response = $this->httpClient->request($monitor->getMethod() ?? 'GET', $monitor->getUrl(), [
                    'timeout' => 10,
                    'verify_peer' => !$monitor->isIgnoreTls(),
                ]);

                $duration = (int)((microtime(true) - $start) * 1000);
                $heartbeat->setResponseTime($duration);

                $statusCode = $response->getStatusCode();
                $isUp = $statusCode < 400;

                // Uptime Kuma Farkı: SSL Takibi
                if (str_starts_with($monitor->getUrl(), 'https://')) {
                    $heartbeat->setSslDaysRemaining($this->getSslRemainingDays($monitor->getUrl()));
                }

                $heartbeat->setStatus($monitor->isUpsideDown() ? !$isUp : $isUp);
                $heartbeat->setMessage("HTTP " . $statusCode);
            }
        } catch (\Exception $e) {
            $heartbeat->setStatus(false);
            $heartbeat->setMessage($e->getMessage());
            $heartbeat->setResponseTime(0);
        }

        $this->em->persist($heartbeat);
        $this->em->flush();

        return $heartbeat;
    }

    private function getSslRemainingDays(string $url): ?int
    {
        try {
            $host = parse_url($url, PHP_URL_HOST);
            $g = stream_context_create(["ssl" => ["capture_peer_cert" => true]]);
            $r = fopen("https://$host", "rb", false, $g);
            $cont = stream_context_get_params($r);
            $cert = openssl_x509_parse($cont["options"]["ssl"]["peer_certificate"]);
            $validTo = $cert['validTo_time_t'];
            return (int)(($validTo - time()) / 86400);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getOverallStatus(): array
    {
        $monitors = $this->getMonitors();
        $data = [];
        $allUp = true;
        $incidents = [];

        foreach ($monitors as $monitor) {
            $historyEntities = $this->getRecentHeartbeats($monitor, 60); // Son 60 check
            
            $history = array_map(fn($h) => [
                'status' => $h->isStatus() ? 1 : 0,
                'ping' => $h->getResponseTime(),
                'time' => $h->getTimestamp()->format('H:i'),
                'msg' => $h->getMessage()
            ], array_reverse($historyEntities));

            $pings = array_filter(array_column($history, 'ping'), fn($p) => $p > 0);
            $avgPing = !empty($pings) ? round(array_sum($pings) / count($pings)) : 0;
            
            $lastHeartbeat = end($historyEntities);
            if ($lastHeartbeat && !$lastHeartbeat->isStatus()) {
                $allUp = false;
                $incidents[] = [
                    'monitor' => $monitor->getName(),
                    'time' => $lastHeartbeat->getTimestamp(),
                    'error' => $lastHeartbeat->getMessage()
                ];
            }

            $data[] = [
                'id' => $monitor->getId(),
                'name' => $monitor->getName(),
                'url' => $monitor->getUrl(),
                'status' => $lastHeartbeat ? ($lastHeartbeat->isStatus() ? 'operational' : 'outage') : 'unknown',
                'history' => $history,
                'avg_ping' => $avgPing,
                'uptime_percentage' => $this->calculateUptime($history),
                'ssl_days' => ($lastHeartbeat instanceof \App\Entity\Heartbeat) ? $lastHeartbeat->getSslDaysRemaining() : null
            ];
        }

        return [
            'status' => $allUp ? 'Operational' : 'Degraded Performance',
            'monitors' => $data,
            'incidents' => $incidents
        ];
    }

    public function createMonitor(array $data): void
    {
        $monitor = new Monitor();
        $monitor->setName($data['name']);
        $monitor->setUrl($data['url']);
        $monitor->setType($data['type'] ?? 'http');
        $monitor->setInterval((int)($data['interval'] ?? 60));
        $monitor->setDescription($data['description'] ?? null);
        
        $this->em->persist($monitor);
        $this->em->flush();
    }

    public function deleteMonitor(int $id): void
    {
        $monitor = $this->em->getRepository(Monitor::class)->find($id);
        if ($monitor) {
            $this->em->remove($monitor);
            $this->em->flush();
        }
    }

    private function calculateUptime(array $history): string
    {
        if (empty($history)) return '100.0%';
        $up = count(array_filter($history, fn($h) => $h['status'] === 1));
        return number_format(($up / count($history)) * 100, 1) . '%';
    }
}
