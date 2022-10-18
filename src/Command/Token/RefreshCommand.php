<?php

namespace App\Command\Token;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:token:refresh',
    description: 'Refresh API Token',
)]
class RefreshCommand extends Command
{
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $cache = new FilesystemAdapter();
        $accessToken = $cache->getItem('api.token');
        $refreshToken = $cache->getItem('api.refresh_token');

        if (!$refreshToken->isHit()) {
            $io->error('Refresh token not found');
            return Command::FAILURE;
        }

        $response = $this->client->request('POST', 'https://auth.tesla.com/oauth2/v3/token', [
            'json' => [
                'grant_type' => 'refresh_token',
                'client_id' => 'ownerapi',
                'refresh_token' => $refreshToken->get(),
                'scope' => 'openid email offline_access'
            ]
        ]);

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            $io->error(sprintf('Refresh token api returned status code %s', $response->getStatusCode()));
            return Command::FAILURE;
        }

        $arrayResponse = $response->toArray();
        if (!array_key_exists('access_token', $arrayResponse) || !array_key_exists('refresh_token', $arrayResponse)) {
            $io->error('Refresh token api returned wrong body format');
            return Command::FAILURE;
        }

        $accessToken->expiresAfter($arrayResponse['expires_in']);
        $accessToken->set($arrayResponse['access_token']);
        $cache->save($accessToken);

        $refreshToken->expiresAfter($arrayResponse['expires_in']);
        $refreshToken->set($arrayResponse['refresh_token']);
        $cache->save($refreshToken);

        $io->success('Api token refreshed successfully');

        return Command::SUCCESS;
    }
}
