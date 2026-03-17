<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;

class PapyrusApiService
{
    private string $baseUrl = 'https://api.papyrus.vip/api/v1/public';
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Make a request with retry logic (3 attempts, exponential backoff).
     */
    private function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;
        $lastException = null;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = Http::timeout(15)->connectTimeout(5)->withHeaders([
                    'X-Api-Key' => $this->apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])->$method($url, $data);

                $response->throw();
                return $response->json() ?? [];
            } catch (RequestException $e) {
                $lastException = $e;
                Log::warning("Papyrus API attempt {$attempt} failed", [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'status' => $e->response?->status(),
                    'body' => $e->response?->body(),
                ]);

                if ($attempt < 3) {
                    sleep(pow(2, $attempt - 1)); // 1s, 2s
                }
            } catch (\Exception $e) {
                $lastException = $e;
                Log::error("Papyrus API unexpected error", [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < 3) {
                    sleep(pow(2, $attempt - 1));
                }
            }
        }

        throw $lastException ?? new \RuntimeException('Papyrus API request failed after 3 attempts');
    }

    public function getAccount(): array
    {
        return $this->request('get', '/account');
    }

    public function getBilling(): array
    {
        return $this->request('get', '/billing');
    }

    public function getUsage(): array
    {
        return $this->request('get', '/usage');
    }

    public function listProxies(): array
    {
        return $this->request('get', '/blaze/networks');
    }

    public function createProxy(array $config): array
    {
        return $this->request('post', '/blaze/networks', $config);
    }

    public function getProxy(string $id): array
    {
        return $this->request('get', "/blaze/networks/{$id}");
    }

    public function updateProxy(string $id, array $config): array
    {
        $current = $this->getProxy($id);
        $currentData = $current['data'] ?? $current;

        // Remove read-only / metadata fields that the API doesn't accept on update
        unset($currentData['_id'], $currentData['id'], $currentData['user'], $currentData['created_at'], $currentData['updated_at'], $currentData['__v']);

        $merged = array_replace($currentData, $config);

        return $this->request('put', "/blaze/networks/{$id}", $merged);
    }

    public function deleteProxy(string $id): array
    {
        return $this->request('delete', "/blaze/networks/{$id}");
    }

    /**
     * Build a full proxy configuration array for the Papyrus API.
     */
    public function buildProxyConfig(
        string $name,
        string $serverShortId,
        string $backendIp,
        int $backendPort,
        bool $disableProxyProtocol,
        int $cpsThreshold,
        array $antibotConfig,
        array $dnsToBindArray,
        string $motdUp,
        string $motdDown,
        bool $chatReports
    ): array {
        return [
            'name' => $serverShortId,
            'description' => $name,
            'tags' => ['pterodactyl', 'papyrus-extension'],
            'cps-to-trigger-antibot' => $cpsThreshold,
            'antibot-config' => $antibotConfig,
            'dns-to-bind' => $dnsToBindArray,
            'instances' => [
                [
                    'name' => 'primary',
                    'host' => $backendIp,
                    'port' => $backendPort,
                    'disable-proxy-protocol' => $disableProxyProtocol,
                ],
            ],
            'motd-up' => $motdUp,
            'motd-down' => $motdDown,
            'enable-prevent-chat-reports-support' => $chatReports,
        ];
    }
}
