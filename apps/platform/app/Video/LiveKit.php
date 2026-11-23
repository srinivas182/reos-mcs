<?php

declare(strict_types=1);

namespace App\Video;

use App\Integrations\Catalogue;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Models\User;
use App\Models\VideoSession;
use App\Models\VideoSessionParticipant;
use App\Services\Integrations\IntegrationHub;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * LiveKit Cloud (B6.3, ADR 0024), with the server URL, API key and secret
 * from Settings › Integrations (`livekit`). REOS mints a token for one
 * person in one room that stops working at the booked end, and closes the
 * room for everyone then. Outside production, until LiveKit is set up, the
 * room is simulated (no media server), so the screens and rules still work.
 */
final readonly class LiveKit
{
    public const INTEGRATION = 'livekit';

    public function __construct(
        private Application $app,
        private Catalogue $catalogue,
        private IntegrationHub $hub,
        private CredentialVault $vault,
        private Http $http,
    ) {}

    /** Whether rooms can be held here (LiveKit set up, or simulated outside production). */
    public function available(): bool
    {
        return $this->keys() !== null || $this->simulated();
    }

    public function simulated(): bool
    {
        return $this->keys() === null && $this->mode() === IntegrationMode::Simulator && ! $this->app->isProduction();
    }

    /**
     * What a person needs to enter the room: the server and their token, or
     * null on the simulator.
     *
     * @return array{url: string, token: string}|null
     */
    public function join(VideoSession $session, User $user, string $role, bool $onStage = false): ?array
    {
        $keys = $this->keys();

        if ($keys === null) {
            return null;
        }

        $host = $role === VideoSessionParticipant::HOST;
        $now = now()->getTimestamp();
        $token = JWT::encode([
            'iss' => $keys['api_key'],
            'sub' => 'user-'.$user->id,
            'name' => $user->name ?? '',
            'nbf' => $now - 10,
            // A token can't be used to join after the booked end.
            'exp' => $session->ends_at->getTimestamp(),
            'metadata' => json_encode(['role' => $role], JSON_THROW_ON_ERROR),
            'video' => [
                'room' => $session->room(),
                'roomJoin' => true,
                'canSubscribe' => true,
                // In a live class only the host, and students the host brought on (A10.2b), may speak.
                'canPublish' => $host || $onStage || $session->kind === VideoSession::ONE_ON_ONE,
                'canPublishData' => true,
                'roomAdmin' => $host,
            ],
        ], $keys['api_secret'], 'HS256');

        return ['url' => $keys['server_url'], 'token' => $token];
    }

    /**
     * Lets a live class student speak and show video, or stops them
     * (A10.2b: the host brings someone with a raised hand on).
     */
    public function setSpeaker(VideoSession $session, User $user, bool $speaker): void
    {
        $keys = $this->keys();

        if ($keys === null) {
            return;
        }

        try {
            $this->twirp($keys, 'livekit.RoomService/UpdateParticipant', [
                'room' => $session->room(),
                'identity' => 'user-'.$user->id,
                'permission' => ['canSubscribe' => true, 'canPublish' => $speaker, 'canPublishData' => true],
            ], ['room' => $session->room(), 'roomAdmin' => true]);
        } catch (Throwable $e) {
            // Not in the room right now: the permission applies when they join.
            report($e);
        }
    }

    /** Closes the room for everyone (the booked end, or the host ended it). */
    public function close(VideoSession $session): void
    {
        $keys = $this->keys();

        if ($keys === null) {
            return;
        }

        $admin = JWT::encode([
            'iss' => $keys['api_key'],
            'nbf' => now()->getTimestamp() - 10,
            'exp' => now()->addMinutes(5)->getTimestamp(),
            'video' => ['room' => $session->room(), 'roomCreate' => true, 'roomAdmin' => true],
        ], $keys['api_secret'], 'HS256');

        try {
            // A room nobody joined doesn't exist; that's fine.
            $this->http->withToken($admin)->acceptJson()->asJson()->connectTimeout(5)->timeout(10)
                ->post(self::twirpUrl($keys['server_url']).'/twirp/livekit.RoomService/DeleteRoom', ['room' => $session->room()]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Starts recording the room to REOS's own storage (B6.3c, LiveKit egress:
     * the room's grid, as MP4). Null on the simulator.
     */
    public function startRecording(VideoSession $session, string $path): ?string
    {
        $keys = $this->keys();

        if ($keys === null) {
            return null;
        }

        $response = $this->twirp($keys, 'livekit.Egress/StartRoomCompositeEgress', [
            'room_name' => $session->room(),
            'layout' => 'grid',
            'file_outputs' => [['file_type' => 'MP4', 'filepath' => $path, 's3' => $this->storage()]],
        ], ['roomRecord' => true]);
        $id = $response['egress_id'] ?? $response['egressId'] ?? null;

        if (! is_string($id) || $id === '') {
            throw new RuntimeException('LiveKit did not start the recording.');
        }

        return $id;
    }

    public function stopRecording(string $egressId): void
    {
        $keys = $this->keys();

        if ($keys !== null) {
            $this->twirp($keys, 'livekit.Egress/StopEgress', ['egress_id' => $egressId], ['roomRecord' => true]);
        }
    }

    /**
     * Whether a webhook came from LiveKit: its Authorization header is a token
     * signed with the API secret whose `sha256` claim is the body's hash.
     */
    public function verifyWebhook(string $body, ?string $authorization): bool
    {
        $keys = $this->keys();

        if ($keys === null || $authorization === null || $authorization === '') {
            return false;
        }

        try {
            $claims = (array) JWT::decode(preg_replace('/^Bearer\s+/i', '', $authorization) ?? '', new Key($keys['api_secret'], 'HS256'));
        } catch (Throwable) {
            return false;
        }

        return ($claims['iss'] ?? null) === $keys['api_key']
            && is_string($claims['sha256'] ?? null)
            && hash_equals(base64_encode(hash('sha256', $body, true)), $claims['sha256']);
    }

    /**
     * Where LiveKit uploads recordings: REOS's content bucket (S3).
     *
     * @return array<string, string|bool>
     */
    private function storage(): array
    {
        $disk = config('filesystems.disks.'.config()->string('reos.content.disk'));
        $disk = is_array($disk) ? $disk : [];
        $text = static fn (string $key): string => is_string($disk[$key] ?? null) ? $disk[$key] : '';

        if (($disk['driver'] ?? null) !== 's3' || $text('key') === '' || $text('secret') === '' || $text('bucket') === '') {
            throw ValidationException::withMessages(['recording' => __('sessions.recording.errors.storage')]);
        }

        return array_filter([
            'access_key' => $text('key'),
            'secret' => $text('secret'),
            'region' => $text('region'),
            'bucket' => $text('bucket'),
            'endpoint' => $text('endpoint'),
            'force_path_style' => (bool) ($disk['use_path_style_endpoint'] ?? false),
        ], static fn ($v): bool => $v !== '');
    }

    /**
     * @param  array{server_url: string, api_key: string, api_secret: string}  $keys
     * @param  array<string, mixed>  $body
     * @param  array<string, bool|string>  $grant
     * @return array<string, mixed>
     */
    private function twirp(array $keys, string $method, array $body, array $grant): array
    {
        $token = JWT::encode([
            'iss' => $keys['api_key'],
            'nbf' => now()->getTimestamp() - 10,
            'exp' => now()->addMinutes(5)->getTimestamp(),
            'video' => $grant,
        ], $keys['api_secret'], 'HS256');

        $response = $this->http->withToken($token)->acceptJson()->asJson()->connectTimeout(5)->timeout(15)
            ->post(self::twirpUrl($keys['server_url']).'/twirp/'.$method, $body);

        if (! $response->successful()) {
            throw new RuntimeException("LiveKit refused {$method} ({$response->status()}).");
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /** wss://example.livekit.cloud → https://example.livekit.cloud */
    public static function twirpUrl(string $url): string
    {
        return rtrim((string) preg_replace('#^ws(s?)://#', 'http$1://', $url), '/');
    }

    private function mode(): IntegrationMode
    {
        $definition = $this->catalogue->find(self::INTEGRATION);

        return $definition === null ? IntegrationMode::Off : $this->hub->status($definition)->effectiveMode();
    }

    /** @return array{server_url: string, api_key: string, api_secret: string}|null */
    private function keys(): ?array
    {
        $environment = $this->mode()->environment();

        if ($environment === null) {
            return null;
        }

        $keys = $this->vault->get(self::INTEGRATION, $environment);
        $url = $keys['server_url'] ?? '';
        $key = $keys['api_key'] ?? '';
        $secret = $keys['api_secret'] ?? '';

        return $url === '' || $key === '' || $secret === '' ? null : ['server_url' => $url, 'api_key' => $key, 'api_secret' => $secret];
    }
}
