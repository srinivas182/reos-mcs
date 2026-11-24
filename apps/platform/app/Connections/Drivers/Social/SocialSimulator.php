<?php

declare(strict_types=1);

namespace App\Connections\Drivers\Social;

use App\Connections\ConnectedAccount;
use App\Connections\ConnectorDriver;
use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Exceptions\VendorUnavailable;
use App\Connections\PublishesPosts;
use App\Connections\SimulatorSignature;
use App\Connections\TokenSet;
use App\Models\Connection;
use App\Models\SocialPostTarget;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * A social channel on demo data (B11). The demo sign-in connects at once;
 * posts "publish" at once (a caption containing "#fail" fails, to show
 * retries) after the channel's own rules are checked; insights grow with
 * the hours since posting, the same every time for the same post.
 */
abstract class SocialSimulator implements ConnectorDriver, PublishesPosts
{
    use PostsOnly;

    /** @return list<array{channel: string, id: string, name: string}> */
    abstract protected function places(): array;

    /**
     * The metrics this channel reports, as shares of reach (reach itself is 1.0).
     *
     * @return array<string, float>
     */
    abstract protected function metrics(): array;

    abstract protected function label(): string;

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
        return new ConnectedAccount($this->integration().'-sim-1', $this->label());
    }

    public function requestsPerMinute(): int
    {
        return 600;
    }

    public function targets(Connection $connection, string $accessToken): array
    {
        if (($connection->meta['simulator_revoked'] ?? false) === true) {
            throw new AccessRevoked('Simulator revoked the connection.');
        }

        return $this->places();
    }

    public function publish(Connection $connection, string $accessToken, array $target, array $content): array
    {
        if (str_contains($content['text'], '#fail')) {
            throw new VendorUnavailable('Simulator: the channel is having trouble.');
        }

        $needs = static::channels()[$target['channel']] ?? null;
        $video = array_filter($content['media'], static fn (array $m): bool => $m['kind'] === 'video') !== [];

        if ($needs !== null && (($needs['media'] === 'required' && $content['media'] === []) || ($needs['media'] === 'video' && ! $video))) {
            throw new VendorUnavailable("{$target['channel']} needs ".($needs['media'] === 'video' ? 'a video.' : 'a photo or video.'));
        }

        return ['id' => 'sim-'.$target['channel'].'-'.Str::lower(Str::random(10)), 'url' => null];
    }

    public function insights(Connection $connection, string $accessToken, array $target, string $externalId): array
    {
        $published = SocialPostTarget::query()->where('external_id', $externalId)->first()?->published_at;
        $hours = $published === null ? 1 : max(1, (int) $published->diffInHours(CarbonImmutable::now()));
        $reach = min(5000, ((int) (crc32($externalId) % 50) + 50) * $hours);
        $out = [];

        foreach ($this->metrics() as $metric => $share) {
            $out[$metric] = (int) ($reach * $share);
        }

        return $out;
    }

    private function tokens(): TokenSet
    {
        return new TokenSet(SimulatorSignature::seal(['account' => $this->integration(), 'kind' => 'access', 'nonce' => Str::random(8)]), SimulatorSignature::seal(['account' => $this->integration(), 'kind' => 'refresh']), CarbonImmutable::now()->addDays(60), []);
    }
}
