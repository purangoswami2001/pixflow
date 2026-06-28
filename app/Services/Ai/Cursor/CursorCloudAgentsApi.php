<?php

namespace App\Services\Ai\Cursor;

use App\Exceptions\AiGenerationException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Low-level client for Cursor Cloud Agents API v1.
 *
 * @see https://cursor.com/docs/cloud-agent/api/endpoints
 */
class CursorCloudAgentsApi
{
    public function getMe(): array
    {
        return $this->request('GET', '/me')->json();
    }

    public function listModels(): array
    {
        return $this->request('GET', '/models')->json('items', []);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createAgent(array $payload): array
    {
        return $this->request('POST', '/agents', $payload)->json();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createRun(string $agentId, array $payload): array
    {
        return $this->request('POST', "/agents/{$agentId}/runs", $payload)->json();
    }

    public function getRun(string $agentId, string $runId): array
    {
        return $this->request('GET', "/agents/{$agentId}/runs/{$runId}")->json();
    }

    public function cancelRun(string $agentId, string $runId): void
    {
        $this->request('POST', "/agents/{$agentId}/runs/{$runId}/cancel");
    }

    public function listArtifacts(string $agentId): array
    {
        return $this->request('GET', "/agents/{$agentId}/artifacts")->json('items', []);
    }

    public function artifactDownloadUrl(string $agentId, string $path): string
    {
        $response = $this->request('GET', "/agents/{$agentId}/artifacts/download", query: [
            'path' => $path,
        ]);

        $url = $response->json('url');
        if (! is_string($url) || $url === '') {
            throw new AiGenerationException('Cursor artifact download URL missing.');
        }

        return $url;
    }

    public function archiveAgent(string $agentId): void
    {
        $this->request('POST', "/agents/{$agentId}/archive");
    }

    public function waitForRunResult(string $agentId, string $runId, ?int $timeoutSeconds = null): string
    {
        $timeout = $timeoutSeconds ?? (int) config('ai.cursor.poll_timeout', 360);
        $interval = max(1, (int) config('ai.cursor.poll_interval', 3));
        $deadline = time() + $timeout;
        $terminal = ['FINISHED', 'ERROR', 'CANCELLED', 'EXPIRED'];

        while (time() < $deadline) {
            $run = $this->getRun($agentId, $runId);
            $status = strtoupper((string) ($run['status'] ?? ''));

            if (in_array($status, $terminal, true)) {
                if ($status !== 'FINISHED') {
                    throw new AiGenerationException("Cursor agent run {$status}.");
                }

                $result = $run['result'] ?? null;
                if (! is_string($result) || trim($result) === '') {
                    throw new AiGenerationException('Cursor agent finished without a result.');
                }

                return $result;
            }

            sleep($interval);
        }

        throw new AiGenerationException('Cursor agent run timed out.');
    }

    protected function request(string $method, string $path, ?array $payload = null, array $query = []): Response
    {
        $url = $this->apiBase().$path;
        $client = $this->http();

        $response = match (strtoupper($method)) {
            'GET' => $client->get($url, $query),
            'POST' => $client->post($url, $payload ?? []),
            'DELETE' => $client->delete($url),
            default => throw new AiGenerationException("Unsupported HTTP method: {$method}"),
        };

        if ($response->successful()) {
            return $response;
        }

        throw new AiGenerationException($this->errorMessage($response));
    }

    protected function errorMessage(Response $response): string
    {
        return $response->json('message')
            ?? $response->json('error.message')
            ?? $response->json('error')
            ?? "Cursor API request failed (HTTP {$response->status()}).";
    }

    protected function http(): PendingRequest
    {
        $request = Http::timeout((int) config('ai.cursor.poll_timeout', 180))
            ->acceptJson()
            ->asJson();

        $key = config('ai.cursor.api_key');
        $auth = strtolower((string) config('ai.cursor.auth', 'basic'));

        if ($auth === 'bearer') {
            return $request->withToken(is_string($key) ? $key : '');
        }

        return $request->withBasicAuth(is_string($key) ? $key : '', '');
    }

    protected function apiBase(): string
    {
        return rtrim((string) config('ai.cursor.api_base_url', 'https://api.cursor.com/v1'), '/');
    }
}
