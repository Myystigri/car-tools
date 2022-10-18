<?php

namespace App\Command\Token;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:token:retrieve',
    description: 'retrieve API Token',
)]
class RetrieveCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $cache = new FilesystemAdapter();

        $cacheItems = $cache->getItems(['api.refresh_token', 'api.token']);

        /** @var CacheItem $cacheItem */
        foreach ($cacheItems as $cacheItem) {
            if (!$cacheItem->isHit()) {
                $io->error(sprintf('%s not found', $cacheItem->getKey()));
            }

            $io->info(sprintf('%s: %s', $cacheItem->getKey(), $cacheItem->get()));
        }

        return Command::SUCCESS;
    }
}
