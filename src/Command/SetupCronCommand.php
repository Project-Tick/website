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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'app:setup:cron',
    description: 'Generates or installs Crontab entries for Project Tick automation.',
)]
class SetupCronCommand extends Command
{
    private string $projectDir;

    public function __construct(ParameterBagInterface $params)
    {
        parent::__construct();
        $this->projectDir = $params->get('kernel.project_dir');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $phpPath = PHP_BINARY;
        $consolePath = $this->projectDir . '/bin/console';

        $markerStart = '# --- BEGIN PROJT AUTOMATION ---';
        $markerEnd   = '# --- END PROJT AUTOMATION ---';

        $newCrons = [
            $markerStart,
            "* * * * * $phpPath $consolePath app:github:bot-scan >> {$this->projectDir}/var/log/cron_bot.log 2>&1",
            "*/5 * * * * $phpPath $consolePath app:gitlab-observer >> {$this->projectDir}/var/log/cron_gitlab.log 2>&1",
            "*/10 * * * * $phpPath $consolePath app:check-cla >> {$this->projectDir}/var/log/cron_cla.log 2>&1",
            "*/2 * * * * $phpPath $consolePath app:monitor:check >> {$this->projectDir}/var/log/cron_monitor.log 2>&1",
            "0 * * * * $phpPath $consolePath app:github:sync-repos >> {$this->projectDir}/var/log/cron_sync.log 2>&1",
            "30 * * * * $phpPath $consolePath app:github:sync-commits >> {$this->projectDir}/var/log/cron_sync.log 2>&1",
            "0 0 * * * $phpPath $consolePath app:cleanup:visits 90 >> {$this->projectDir}/var/log/cron_sync.log 2>&1",
            $markerEnd,
        ];

        // 1. Get current crontab using Symfony Process (shell_exec is disabled)
        $process = new Process(['crontab', '-l']);
        $process->run();
        $currentCrontab = $process->isSuccessful() ? $process->getOutput() : '';
        
        $lines = explode("\n", trim($currentCrontab));
        
        // 2. Filter out old ProjT block if exists
        $keptLines = [];
        $skip = false;
        foreach ($lines as $line) {
            if (trim($line) === $markerStart) {
                $skip = true;
                continue;
            }
            if (trim($line) === $markerEnd) {
                $skip = false;
                continue;
            }
            if (!$skip && !empty($line)) {
                // Also clean up that old legacy comment we used before markers
                if (str_contains($line, '# Project Tick Automation Suite')) continue;
                // If it's one of our console commands without markers (legacy cleanup)
                if (str_contains($line, $consolePath)) continue;

                $keptLines[] = $line;
            }
        }

        // 3. Merge
        $finalCrontab = implode("\n", array_merge($keptLines, $newCrons)) . "\n";

        $io->section('Final Crontab Preview (Safety First!):');
        $io->text($finalCrontab);

        if ($io->confirm('Do you want to INSTALL this crontab now (it will preserve non-ProjT jobs)?', true)) {
            $tempFile = $this->projectDir . '/var/projt_crontab_safe.txt';
            if (!is_dir($this->projectDir . '/var')) mkdir($this->projectDir . '/var', 0777, true);
            
            file_put_contents($tempFile, $finalCrontab);
            
            $installProcess = new Process(['crontab', $tempFile]);
            $installProcess->run();

            if ($installProcess->isSuccessful()) {
                $io->success("Crontab successfully updated & preserved other jobs.");
            } else {
                $io->error("Failed to install crontab: " . $installProcess->getErrorOutput());
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}
