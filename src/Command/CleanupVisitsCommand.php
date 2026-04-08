<?php

namespace App\Command;

use App\Entity\Visit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup:visits',
    description: 'Deletes visit records older than a specific number of days.',
)]
class CleanupVisitsCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->addArgument('days', InputArgument::OPTIONAL, 'Number of days to keep', 90);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getArgument('days');
        $date = new \DateTimeImmutable("-$days days");

        $io->note(sprintf('Cleaning up visits older than %d days (%s)', $days, $date->format('Y-m-d H:i:s')));

        $query = $this->entityManager->createQuery(
            'DELETE FROM App\Entity\Visit v WHERE v.visitedAt < :date'
        )->setParameter('date', $date);

        $deletedCount = $query->execute();

        $io->success(sprintf('Deleted %d old visit records.', $deletedCount));

        return Command::SUCCESS;
    }
}
