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
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as Http;
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
    public function join(VideoSession $session, User $user, string $role): ?array
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
                // In a live class only the host is on camera (speakers arrive with B6.3b).
                'canPublish' => $host || $session->kind === VideoSession::ONE_ON_ONE,
                'canPublishData' => true,
                'roomAdmin' => $host,
            ],
        ], $keys['api_secret'], 'HS256');

        return ['url' => $keys['server_url'], 'token' => $token];
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
                ->post(self::twirp($keys['server_url']).'/twirp/livekit.RoomService/DeleteRoom', ['room' => $session->room()]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** wss://example.livekit.cloud → https://example.livekit.cloud */
    public static function twirp(string $url): string
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
