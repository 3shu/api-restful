<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use App\Shared\Infrastructure\Database\ConnectionManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-connections',
    description: 'Test all database connections'
)]
final class TestConnectionsCommand extends Command
{
    public function __construct(
        private readonly ConnectionManager $connectionManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Database Connections Health Check');

        $connections = [
            'api-restful/mysql_users' => 'MySQL - Users Database',
            'api-restful/postgres_books' => 'PostgreSQL - Books Database',
        ];

        $allHealthy = true;

        foreach ($connections as $name => $description) {
            $io->section($description);

            try {
                $connection = $this->connectionManager->getConnection($name);

                $io->writeln(sprintf('Connection Name: <info>%s</info>', $connection->getName()));
                $io->writeln(sprintf('Connection Type: <info>%s</info>', $connection->getType()));

                if ($connection->isConnected()) {
                    $io->success('Connection established successfully!');

                    // Test query for SQL databases
                    if (in_array($connection->getType(), ['mysql', 'postgres'])) {
                        $conn = $connection->getConnection();
                        $result = $conn->executeQuery('SELECT 1 as test')->fetchOne();
                        $io->writeln(sprintf('Query test result: <info>%s</info>', $result));
                    }
                } else {
                    $io->error('Connection failed!');
                    $allHealthy = false;
                }
            } catch (\Exception $e) {
                $io->error(sprintf('Error: %s', $e->getMessage()));
                $allHealthy = false;
            }
        }

        $io->newLine();
        
        if ($allHealthy) {
            $io->success('All connections are healthy!');
            return Command::SUCCESS;
        } else {
            $io->warning('Some connections failed. Please check the errors above.');
            return Command::FAILURE;
        }
    }
}
