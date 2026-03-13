<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Http\Client\RequestException;

class CloudflareApiService
{
    private string $baseUrl = 'https://api.cloudflare.com/client/v4';
    private string $apiKey;
    private string $zoneId;

    public function __construct(string $apiKey, string $zoneId)
    {
        $this->apiKey = $apiKey;
        $this->zoneId = $zoneId;
    }

    /**
     * Make a Cloudflare API request with retry logic.
     */
    private function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;
        $lastException = null;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = Http::timeout(15)->connectTimeout(5)->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])->$method($url, $data);

                $response->throw();
                $body = $response->json();

                if (!($body['success'] ?? false)) {
                    $errors = collect($body['errors'] ?? [])->pluck('message')->implode(', ');
                    throw new \RuntimeException("Cloudflare API error: {$errors}");
                }

                return $body;
            } catch (RequestException $e) {
                $lastException = $e;
                Log::warning("Cloudflare API attempt {$attempt} failed", [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'status' => $e->response?->status(),
                    'body' => $e->response?->body(),
                ]);

                if ($attempt < 3) {
                    sleep(pow(2, $attempt - 1));
                }
            } catch (\Exception $e) {
                $lastException = $e;
                Log::error("Cloudflare API error", [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < 3) {
                    sleep(pow(2, $attempt - 1));
                }
            }
        }

        throw $lastException ?? new \RuntimeException('Cloudflare API request failed after 3 attempts');
    }

    /**
     * Validate that the API key + zone ID grant access.
     */
    public function validateAccess(): bool
    {
        try {
            $this->request('get', "/zones/{$this->zoneId}");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create a DNS record. Uses CNAME by default, A if target is an IP.
     * CRITICAL: proxied must be false — Cloudflare HTTP proxy breaks Minecraft TCP.
     */
    public function createDnsRecord(string $subdomain, string $target, string $type = 'CNAME'): array
    {
        if (filter_var($target, FILTER_VALIDATE_IP)) {
            $type = 'A';
        }

        $response = $this->request('post', "/zones/{$this->zoneId}/dns_records", [
            'type' => $type,
            'name' => $subdomain,
            'content' => $target,
            'proxied' => false,
            'ttl' => 300,
        ]);

        return $response['result'] ?? [];
    }

    /**
     * Delete a DNS record by its Cloudflare record ID.
     */
    public function deleteDnsRecord(string $recordId): bool
    {
        try {
            $this->request('delete', "/zones/{$this->zoneId}/dns_records/{$recordId}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete Cloudflare DNS record", [
                'record_id' => $recordId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Update an existing DNS record.
     */
    public function updateDnsRecord(string $recordId, string $subdomain, string $target): array
    {
        $type = filter_var($target, FILTER_VALIDATE_IP) ? 'A' : 'CNAME';

        $response = $this->request('put', "/zones/{$this->zoneId}/dns_records/{$recordId}", [
            'type' => $type,
            'name' => $subdomain,
            'content' => $target,
            'proxied' => false,
            'ttl' => 300,
        ]);

        return $response['result'] ?? [];
    }

    /**
     * Generate a subdomain from a pattern, handling collisions.
     */
    public function generateSubdomain(string $pattern, $server, string $baseDomain): string
    {
        $subdomain = str_replace(
            ['{server_name}', '{server_id}', '{server_uuid}'],
            [
                Str::slug($server->name),
                $server->uuidShort ?? substr($server->uuid, 0, 8),
                substr($server->uuid, 0, 8),
            ],
            $pattern
        );

        $candidate = $subdomain . '.' . $baseDomain;

        try {
            $existing = $this->request('get', "/zones/{$this->zoneId}/dns_records", [
                'name' => $candidate,
            ]);

            if (!empty($existing['result'])) {
                for ($i = 2; $i <= 99; $i++) {
                    $candidate = $subdomain . '-' . $i . '.' . $baseDomain;
                    $check = $this->request('get', "/zones/{$this->zoneId}/dns_records", [
                        'name' => $candidate,
                    ]);
                    if (empty($check['result'])) {
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to check for subdomain collision', ['error' => $e->getMessage()]);
        }

        return $candidate;
    }
}
