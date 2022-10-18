<?php

namespace App\Command;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:charging',
    description: 'Control vehicle charging state',
)]
class ChargingCommand extends Command
{
    private const ACTION_STATUS = 'status';
    private const ACTION_STOP = 'stop';
    private const ACTION_START = 'start';

    private string $apiUrl;
    private HttpClientInterface $client;
    private string $apiToken = '';

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Action to execute')
        ;
    }

    public function __construct(string $apiUrl, HttpClientInterface $client)
    {
        $this->apiUrl = $apiUrl;
        $this->client = $client;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');

        $cache = new FilesystemAdapter();
        $apiToken = $cache->getItem('api.token');

        if (!$apiToken->isHit()) {
            $io->error('Api token not found');
            return Command::FAILURE;
        }

        $this->apiToken = $apiToken->get();

        switch ($action) {
            case self::ACTION_STATUS:
                $io->note('Checking vehicle current charging status');
                $currentStatus = $this->status();
                $io->success(sprintf('vehicle current charging status: %s', $currentStatus));
                break;
            case self::ACTION_START:
                $io->note('Attempting to start vehicle charging');
                $this->start();
                $io->success('Vehicle charging started successfully');
                break;
            case self::ACTION_STOP:
                $io->note('Attempting to stop vehicle charging');
                $this->stop();
                $io->success('Vehicle charging stopped successfully');
                break;
            default:
                $io->error(sprintf('Unknown command: $%s', $action));
                return Command::INVALID;
        }

        return Command::SUCCESS;
    }

    private function status(): string
    {
        return '';
    }

    private function start(): void
    {

    }

    private function stop(): void
    {

    }
}
