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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:version',
    description: 'Manage Project Tick Website versioning.',
)]
class VersionCommand extends Command
{
    private string $projectDir;

    public function __construct(ParameterBagInterface $params)
    {
        parent::__construct();
        $this->projectDir = $params->get('kernel.project_dir');
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::OPTIONAL, 'Action: show, bump-patch, bump-minor, bump-major', 'show')
            ->addArgument('tag', InputArgument::OPTIONAL, 'Set a custom tag (e.g. STABLE, BETA, RC1)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');
        $tag = $input->getArgument('tag');
        
        $versionFile = $this->projectDir . '/VERSION';
        $currentVersion = '0.0.0-UNKNOWN';
        
        if (file_exists($versionFile)) {
            $currentVersion = trim(file_get_contents($versionFile));
        }

        if ($action === 'show') {
            $io->title('Current System Version');
            $io->success("v" . $currentVersion);
            return Command::SUCCESS;
        }

        // Parse Semantic Versioning (X.Y.Z-TAG)
        $parts = explode('-', $currentVersion);
        $numbers = explode('.', $parts[0]);
        $major = isset($numbers[0]) ? (int)$numbers[0] : 0;
        $minor = isset($numbers[1]) ? (int)$numbers[1] : 0;
        $patch = isset($numbers[2]) ? (int)$numbers[2] : 0;
        $currentTag = $parts[1] ?? 'STABLE';

        if ($tag) {
            $currentTag = strtoupper($tag);
        }

        switch ($action) {
            case 'bump-patch':
                $patch++;
                break;
            case 'bump-minor':
                $minor++;
                $patch = 0;
                break;
            case 'bump-major':
                $major++;
                $minor = 0;
                $patch = 0;
                break;
            default:
                $io->error("Invalid action: $action. Use bump-patch, bump-minor, or bump-major.");
                return Command::FAILURE;
        }

        $newVersion = sprintf("%d.%d.%d-%s", $major, $minor, $patch, $currentTag);
        file_put_contents($versionFile, $newVersion);

        $io->success("System version bumped to: v" . $newVersion);
        
        // Try to update CHANGELOG.md if exists
        $changelogFile = $this->projectDir . '/CHANGELOG.md';
        if (file_exists($changelogFile)) {
            $date = date('Y-m-d');
            $entry = "\n## [{$newVersion}] - {$date}\n- Manual version bump via CLI.\n";
            file_put_contents($changelogFile, $entry, FILE_APPEND);
            $io->text("Appended entry to CHANGELOG.md");
        }

        return Command::SUCCESS;
    }
}
