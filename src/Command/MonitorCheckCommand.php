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

namespace App\Command;

use App\Module\Monitor\UptimeService;
use App\Service\ModuleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:monitor:check',
    description: 'Project Tick Intelligence - Advanced Health Checks',
)]
class MonitorCheckCommand extends Command
{
    public function __construct(
        private UptimeService $uptimeService,
        private ModuleService $modules
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->modules->isMonitorEnabled()) {
            $io->warning('Monitor module is DISABLED. Skipping health checks.');
            return Command::SUCCESS;
        }
        $monitors = $this->uptimeService->getMonitors();

        foreach ($monitors as $monitor) {
            if (!$monitor->isActive()) continue;

            $io->text("Scanning: " . $monitor->getName());
            $heartbeat = $this->uptimeService->performCheck($monitor);
            
            $statusText = $heartbeat->isStatus() ? 'UP' : 'DOWN';
            $color = $heartbeat->isStatus() ? 'info' : 'error';
            
            $sslInfo = $heartbeat->getSslDaysRemaining() !== null 
                ? " (SSL: " . $heartbeat->getSslDaysRemaining() . " days)" 
                : "";

            $io->writeln(" -> <$color>$statusText</$color> [{$heartbeat->getResponseTime()}ms]$sslInfo");
        }

        return Command::SUCCESS;
    }
}
