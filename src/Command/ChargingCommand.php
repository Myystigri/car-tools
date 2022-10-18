<?php

namespace App\Command;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Response;
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
    private const ACTION_WAKE = 'wake';

    private HttpClientInterface $client;
    private string $vehicleId;

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Action to execute')
        ;
    }

    public function __construct(HttpClientInterface $client, string $apiUrl, string $vehicleId)
    {
        $cache = new FilesystemAdapter();
        $apiToken = $cache->getItem('api.token');
        if (!$apiToken->isHit()) {
            throw new \Exception('Api token not found');
        }

        $this->client = $client->withOptions([
            'base_uri' => $apiUrl,
            'headers' => [
                'Authorization' => 'Bearer '.$apiToken->get(),
                'Accept' => 'application/json'
            ]
        ]);
        $this->vehicleId = $vehicleId;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');

        try {
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
                case self::ACTION_WAKE:
                    $io->note('Attempting to wake vehicle');
                    $this->wake();
                    break;
                default:
                    $io->error(sprintf('Unknown command: $%s', $action));
                    return Command::INVALID;
            }
        } catch (\Exception $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function status(): string
    {
        $response = $this->client->request('GET', 'api/1/vehicles/'.$this->vehicleId.'/data_request/charge_state');

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw new \Exception(sprintf('Charging status api returned status code %s', $response->getStatusCode()));
        }

        $responseArray = $response->toArray();
        if (!array_key_exists('response', $responseArray)
            || !array_key_exists('battery_level', $responseArray['response'])
        ) {
            throw new \Exception('Charging status api returned wrong body format');
        }

        return $responseArray['response']['battery_level'];
    }

    private function start(): void
    {
        $response = $this->client->request('POST', 'api/1/vehicles/'.$this->vehicleId.'/command/charge_start');

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw new \Exception(sprintf('Charging start api returned status code %s', $response->getStatusCode()));
        }

        $responseArray = $response->toArray();
        if (!array_key_exists('response', $responseArray) || !array_key_exists('result', $responseArray['response'])) {
            throw new \Exception('Charging start api returned wrong body format');
        }

        if ($responseArray['response']['result'] !== true) {
            throw new \Exception(sprintf('Charging start api failed to start charging: %s', $responseArray['response']['reason']));
        }
    }

    private function stop(): void
    {
        $response = $this->client->request('POST', 'api/1/vehicles/'.$this->vehicleId.'/command/charge_stop');

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw new \Exception(sprintf('Charging stop api returned status code %s', $response->getStatusCode()));
        }

        $responseArray = $response->toArray();
        if (!array_key_exists('response', $responseArray) || !array_key_exists('result', $responseArray['response'])) {
            throw new \Exception('Charging stop api returned wrong body format');
        }

        if ($responseArray['response']['result'] !== true) {
            throw new \Exception(sprintf('Charging stop api failed to stop charging: %s', $responseArray['response']['reason']));
        }
    }

    private function wake(): void
    {
        $response = $this->client->request('POST', 'api/1/vehicles/'.$this->vehicleId.'/wake_up');

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw new \Exception(sprintf('Wake api returned status code %s', $response->getStatusCode()));
        }

        $responseArray = $response->toArray();
        if (!array_key_exists('response', $responseArray)) {
            throw new \Exception('Wake api returned wrong body format');
        }
    }
}
