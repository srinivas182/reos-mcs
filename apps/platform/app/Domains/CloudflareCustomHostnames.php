<?php

declare(strict_types=1);

namespace App\Domains;

use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use RuntimeException;

/**
 * Cloudflare for SaaS custom hostnames (A4.2b): the tenant points a CNAME
 * at REOS and proves ownership with a TXT record; Cloudflare issues and
 * renews the SSL certificate.
 *
 * @see https://developers.cloudflare.com/cloudflare-for-platforms/cloudflare-for-saas/
 */
final readonly class CloudflareCustomHostnames implements CustomHostnames
{
    private const API = 'https://api.cloudflare.com/client/v4';

    public function __construct(
        private Http $http,
        private string $token,
        private string $zoneId,
        private string $cnameTarget,
    ) {}

    public function create(string $hostname): HostnameState
    {
        return $this->state($this->call('post', 'custom_hostnames', [
            'hostname' => $hostname,
            'ssl' => ['method' => 'txt', 'type' => 'dv', 'settings' => ['min_tls_version' => '1.2']],
        ]), $hostname);
    }

    public function refresh(string $providerId, string $hostname): HostnameState
    {
        return $this->state($this->call('get', 'custom_hostnames/'.rawurlencode($providerId)), $hostname);
    }

    public function delete(string $providerId): void
    {
        $this->call('delete', 'custom_hostnames/'.rawurlencode($providerId));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function call(string $method, string $path, array $body = []): array
    {
        $request = $this->client();
        $url = self::API.'/zones/'.rawurlencode($this->zoneId).'/'.$path;
        $response = match ($method) {
            'post' => $request->post($url, $body),
            'delete' => $request->delete($url),
            default => $request->get($url),
        };

        $json = $response->json();

        if (! $response->successful() || ! is_array($json) || ($json['success'] ?? false) !== true) {
            $errors = is_array($json) && is_array($json['errors'] ?? null) ? $json['errors'] : [];
            $error = is_array($errors[0] ?? null) ? ($errors[0]['message'] ?? null) : null;

            throw new RuntimeException('Cloudflare: '.(is_string($error) ? $error : 'HTTP '.$response->status()));
        }

        $result = $json['result'] ?? [];

        return is_array($result) ? $result : [];
    }

    private function client(): PendingRequest
    {
        return $this->http->withToken($this->token)->acceptJson()->timeout(15)->retry(2, 500, throw: false);
    }

    /** @param  array<string, mixed>  $result */
    private function state(array $result, string $hostname): HostnameState
    {
        $ownership = is_array($result['ownership_verification'] ?? null) ? $result['ownership_verification'] : [];
        $ssl = is_array($result['ssl'] ?? null) ? $result['ssl'] : [];
        $records = [['type' => 'CNAME', 'name' => $hostname, 'value' => $this->cnameTarget]];

        if (is_string($ownership['name'] ?? null) && is_string($ownership['value'] ?? null)) {
            $records[] = ['type' => 'TXT', 'name' => $ownership['name'], 'value' => $ownership['value']];
        }

        foreach (is_array($ssl['validation_records'] ?? null) ? $ssl['validation_records'] : [] as $record) {
            if (is_array($record) && is_string($record['txt_name'] ?? null) && is_string($record['txt_value'] ?? null)) {
                $records[] = ['type' => 'TXT', 'name' => $record['txt_name'], 'value' => $record['txt_value']];
            }
        }

        $status = match ($result['status'] ?? null) {
            'active' => HostnameState::ACTIVE,
            'blocked', 'moved', 'deleted', 'test_failed' => HostnameState::FAILED,
            default => HostnameState::PENDING,
        };
        $errors = is_array($ssl['validation_errors'] ?? null) ? $ssl['validation_errors'] : [];
        $first = is_array($errors[0] ?? null) ? ($errors[0]['message'] ?? null) : null;

        return new HostnameState(
            providerId: is_string($result['id'] ?? null) ? $result['id'] : '',
            status: $status,
            ssl: ($ssl['status'] ?? null) === 'active' ? HostnameState::ACTIVE : HostnameState::PENDING,
            records: $records,
            error: is_string($first) ? $first : null,
        );
    }
}
