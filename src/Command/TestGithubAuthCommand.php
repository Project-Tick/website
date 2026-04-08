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

use App\Service\GithubTokenBalancer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-github-auth',
    description: 'Tests GitHub authentication for bot and scanner.',
)]
class TestGithubAuthCommand extends Command
{
    private GithubTokenBalancer $balancer;

    public function __construct(GithubTokenBalancer $balancer)
    {
        parent::__construct();
        $this->balancer = $balancer;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('GitHub Auth Test');

        // Test Bot
        $io->section('Testing Bot Identity (GitHub App)');
        try {
            $client = $this->balancer->getBotClient();
            $info = $client->api('apps')->getAuthenticatedApp();
            $io->success('Bot Auth OK! App Name: ' . $info['name']);
        } catch (\Exception $e) {
            $io->error('Bot Auth Failed: ' . $e->getMessage());
        }

        // Test Scanner
        $io->section('Testing Scanner Pool (PATs)');
        try {
            $client = $this->balancer->getScannerClient();
            $info = $client->api('user')->show();
            $io->success('Scanner Auth OK! User: ' . $info['login']);
        } catch (\Exception $e) {
            $io->error('Scanner Auth Failed: ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
