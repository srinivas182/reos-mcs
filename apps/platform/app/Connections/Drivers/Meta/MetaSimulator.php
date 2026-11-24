<?php

declare(strict_types=1);

namespace App\Connections\Drivers\Meta;

use App\Connections\ConnectedAccount;
use App\Connections\ConnectorDriver;
use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Exceptions\VendorUnavailable;
use App\Connections\PublishesPosts;
use App\Connections\SimulatorSignature;
use App\Connections\SyncPage;
use App\Connections\TokenSet;
use App\Connections\WebhookDelivery;
use App\Models\Connection;
use App\Models\SocialPostTarget;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Instagram and Facebook on demo data (B11.1). The demo sign-in connects at
 * once to one Facebook Page with its Instagram account; posts "publish" at
 * once (a caption containing "#fail" fails, to show retries), and insights
 * grow with the hours since posting.
 */
final class MetaSimulator implements ConnectorDriver, PublishesPosts
{
    public const PAGE = 'sim-page-1';

    public const INSTAGRAM = 'sim-ig-1';

    public function integration(): string
    {
        return MetaDriver::INTEGRATION;
    }

    public static function channels(): array
    {
        return MetaDriver::channels();
    }

    public function authorizationUrl(string $state, string $codeChallenge, string $redirectUri): string
    {
        return route('connections.simulator', ['tool' => $this->integration(), 'state' => $state, 'code_challenge' => $codeChallenge, 'redirect_uri' => $redirectUri]);
    }

    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): TokenSet
    {
        $claims = SimulatorSignature::open($code);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        if ($claims === null || ! is_string($claims['challenge'] ?? null) || ! hash_equals($claims['challenge'], $challenge) || ($claims['redirect_uri'] ?? null) !== $redirectUri) {
            throw new AccessRevoked('Simulator refused the code.');
        }

        return $this->tokens();
    }

    public function refresh(string $refreshToken): TokenSet
    {
        return SimulatorSignature::open($refreshToken) === null ? throw new AccessRevoked('Simulator refused the refresh token.') : $this->tokens();
    }

    public function revoke(string $token): void {}

    public function account(TokenSet $tokens): ConnectedAccount
    {
        return new ConnectedAccount('meta-sim-1', 'James Miller (demo)');
    }

    public function streams(): array
    {
        return [];
    }

    public function fetch(Connection $connection, string $accessToken, string $stream, ?string $cursor, ?CarbonImmutable $since): SyncPage
    {
        return new SyncPage([], [], null, true);
    }

    public function requestsPerMinute(): int
    {
        return 600;
    }

    public function verifyWebhook(Request $request): ?WebhookDelivery
    {
        return null;
    }

    public function targets(Connection $connection, string $accessToken): array
    {
        if (($connection->meta['simulator_revoked'] ?? false) === true) {
            throw new AccessRevoked('Simulator revoked the connection.');
        }

        return [
            ['channel' => 'facebook', 'id' => self::PAGE, 'name' => 'James Miller, REALTOR® (demo Page)'],
            ['channel' => 'instagram', 'id' => self::INSTAGRAM, 'name' => '@jamesmiller.homes (demo)'],
        ];
    }

    public function publish(Connection $connection, string $accessToken, array $target, array $content): array
    {
        if (str_contains($content['text'], '#fail')) {
            throw new VendorUnavailable('Simulator: the channel is having trouble.');
        }

        if ($target['channel'] === 'instagram' && $content['media'] === []) {
            throw new VendorUnavailable('Instagram needs a photo or video.');
        }

        $id = ($target['channel'] === 'instagram' ? 'sim-ig-' : 'sim-fb-').Str::lower(Str::random(10));

        return ['id' => $id, 'url' => null];
    }

    /** Insights grow with the hours since posting, the same every time for the same post. */
    public function insights(Connection $connection, string $accessToken, array $target, string $externalId): array
    {
        $published = SocialPostTarget::query()->where('external_id', $externalId)->first()?->published_at;
        $hours = $published === null ? 1 : max(1, (int) $published->diffInHours(CarbonImmutable::now()));
        $seed = (int) (crc32($externalId) % 50) + 50;
        $reach = min(5000, $seed * $hours);
        $metrics = ['reach' => $reach, 'likes' => intdiv($reach, 12), 'comments' => intdiv($reach, 60)];

        return $target['channel'] === 'instagram' ? [...$metrics, 'views' => (int) ($reach * 1.4)] : [...$metrics, 'clicks' => intdiv($reach, 25)];
    }

    private function tokens(): TokenSet
    {
        return new TokenSet(SimulatorSignature::seal(['account' => 'meta', 'kind' => 'access', 'nonce' => Str::random(8)]), SimulatorSignature::seal(['account' => 'meta', 'kind' => 'refresh']), CarbonImmutable::now()->addDays(60), MetaDriver::SCOPES);
    }
}
