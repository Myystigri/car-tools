<?php

namespace App\Command\Token;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:token:set',
    description: 'set API Token',
)]
class SetCommand extends Command
{
    private const TOKEN_TYPE_REFRESH = 'refresh';
    private const TOKEN_TYPE_ACCESS = 'access';

    protected function configure(): void
    {
        $this
            ->addArgument('token', InputArgument::REQUIRED, 'token to set')
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'type of token', self::TOKEN_TYPE_ACCESS)
        ;
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $token = $input->getArgument('token');
        $type = $input->getOption('type');

        if (!$token) {
            $io->error('Missing token to set');

            return Command::INVALID;
        }

        if (!in_array($type, [self::TOKEN_TYPE_ACCESS, self:: TOKEN_TYPE_REFRESH])) {
            $io->error(sprintf('Unknown token type: %s', $type));

            return Command::INVALID;
        }

        $this->saveTokenToCache($token, $type);

        $io->success(sprintf('%s token saved successfully', $type));

        return Command::SUCCESS;
    }

    private function saveTokenToCache(string $token, string $type): void
    {
        $cache = new FilesystemAdapter();

        $name = match ($type) {
            self::TOKEN_TYPE_REFRESH => 'api.refresh_token',
            default => 'api.token',
        };

        $cacheItem = $cache->getItem($name);

        $cacheItem->set($token);
        $cache->save($cacheItem);
    }
}
