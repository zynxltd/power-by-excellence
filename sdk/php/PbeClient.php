<?php

namespace PowerByExcellence;

use Illuminate\Support\Facades\Http;

/**
 * Lightweight PHP client for the PowerByExcellence Lead API.
 *
 * Usage:
 *   $client = new PbeClient('pk_live_...', 'https://your-domain.test/api/v1');
 *   $result = $client->ingestLead(['campaign_ref' => 'auto-insurance-uk', 'email' => 'a@b.com', 'sync' => true]);
 */
class PbeClient
{
    public function __construct(
        protected string $apiKey,
        protected string $baseUrl = '/api/v1',
    ) {}

    public function ingestLead(array $payload): array
    {
        return $this->request('POST', '/leads', $payload);
    }

    public function pollQueue(string $queueId): array
    {
        return $this->request('GET', "/leads/queue/{$queueId}");
    }

    public function getLead(string $uuid): array
    {
        return $this->request('GET', "/leads/{$uuid}");
    }

    public function searchLeads(array $criteria): array
    {
        return $this->request('POST', '/leads/search', $criteria);
    }

    protected function request(string $method, string $path, ?array $body = null): array
    {
        $url = rtrim($this->baseUrl, '/').$path;

        $pending = Http::withToken($this->apiKey)
            ->acceptJson()
            ->timeout(30);

        $response = match (strtoupper($method)) {
            'POST' => $pending->post($url, $body ?? []),
            default => $pending->get($url),
        };

        $response->throw();

        return $response->json();
    }
}
