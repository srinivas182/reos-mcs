<?php

declare(strict_types=1);

namespace App\Connections\Drivers\TikTok;

use App\Connections\ConnectedAccount;
use App\Connections\ConnectorDriver;
use App\Connections\Drivers\Social\PostsOnly;
use App\Connections\Drivers\Social\SocialHttp;
use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Exceptions\VendorUnavailable;
use App\Connections\PublishesPosts;
use App\Connections\TokenSet;
use App\Models\Connection;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * TikTok (B11.3, ADR 0046) through the Content Posting API.
 *
 * - **Sign-in:** TikTok's OAuth 2 with PKCE (it names the client
 *   `client_key`).
 * - **Publishing:** "pull from URL": TikTok fetches the post's video from
 *   REOS's signed link (REOS's domain is verified with TikTok). Until the
 *   Content Posting audit is passed, posts are private ("only me"), as
 *   TikTok requires; `services.tiktok.public_posts` turns public posting on
 *   after the audit.
 * - **Insights:** views, likes, comments and shares from `video/query`.
 */
final readonly class TikTokDriver implements ConnectorDriver, PublishesPosts
{
    use PostsOnly;
    use SocialHttp;

    public const INTEGRATION = 'tiktok';

    public const API = 'https://open.tiktokapis.com/v2';

    public const STREAMS = [];

    public const CONSENT = ['can' => ['list_pages', 'publish_posts', 'read_insights'], 'never' => ['post_without_you', 'read_messages']];

    public const SCOPES = ['user.info.basic', 'video.publish', 'video.list'];

    public function __construct(private string $clientKey, private string $clientSecret, private bool $public = false) {}

    public function integration(): string
    {
        return self::INTEGRATION;
    }

    public static function channels(): array
    {
        return ['tiktok' => ['media' => 'video', 'max_text' => 2200]];
    }

    public function authorizationUrl(string $state, string $codeChallenge, string $redirectUri): string
    {
        return 'https://www.tiktok.com/v2/auth/authorize/?'.http_build_query([
            'client_key' => $this->clientKey, 'response_type' => 'code', 'scope' => implode(',', self::SCOPES), 'redirect_uri' => $redirectUri,
            'state' => $state, 'code_challenge' => $codeChallenge, 'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): TokenSet
    {
        return $this->token(['grant_type' => 'authorization_code', 'code' => $code, 'code_verifier' => $codeVerifier, 'redirect_uri' => $redirectUri]);
    }

    public function refresh(string $refreshToken): TokenSet
    {
        return $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]);
    }

    public function revoke(string $token): void
    {
        Http::asForm()->timeout(10)->post(self::API.'/oauth/revoke/', ['client_key' => $this->clientKey, 'client_secret' => $this->clientSecret, 'token' => $token]);
    }

    public function account(TokenSet $tokens): ConnectedAccount
    {
        $user = $this->data($this->http($tokens->accessToken)->get(self::API.'/user/info/', ['fields' => 'open_id,display_name']))['user'] ?? null;
        $id = is_array($user) ? ($user['open_id'] ?? null) : null;

        if (! is_string($id) || $id === '') {
            throw new AccessRevoked('TikTok returned no user.');
        }

        return new ConnectedAccount($id, is_string($user['display_name'] ?? null) ? $user['display_name'] : 'TikTok');
    }

    public function requestsPerMinute(): int
    {
        // Video posting is limited to a few posts a minute per user.
        return 6;
    }

    public function targets(Connection $connection, string $accessToken): array
    {
        return [['channel' => 'tiktok', 'id' => $connection->external_account_id, 'name' => $connection->account_label ?? 'TikTok']];
    }

    public function publish(Connection $connection, string $accessToken, array $target, array $content): array
    {
        $video = array_values(array_filter($content['media'], static fn (array $m): bool => $m['kind'] === 'video'))[0] ?? throw new VendorUnavailable('TikTok needs a video.');
        $creator = $this->data($this->http($accessToken)->post(self::API.'/post/publish/creator_info/query/'));
        $options = is_array($creator['privacy_level_options'] ?? null) ? $creator['privacy_level_options'] : [];
        $privacy = $this->public && in_array('PUBLIC_TO_EVERYONE', $options, true) ? 'PUBLIC_TO_EVERYONE' : 'SELF_ONLY';
        $init = $this->data($this->http($accessToken)->post(self::API.'/post/publish/video/init/', [
            'post_info' => ['title' => mb_substr($content['text'], 0, 2200), 'privacy_level' => $privacy, 'disable_comment' => false, 'disable_duet' => false, 'disable_stitch' => false],
            'source_info' => ['source' => 'PULL_FROM_URL', 'video_url' => $video['url']],
        ]));
        $publish = $init['publish_id'] ?? null;

        if (! is_string($publish)) {
            throw new VendorUnavailable('TikTok returned no publish id.');
        }

        // TikTok fetches and processes the video; wait a little for its post id.
        for ($i = 0; $i < 20; $i++) {
            $status = $this->data($this->http($accessToken)->post(self::API.'/post/publish/status/fetch/', ['publish_id' => $publish]));
            $state = $status['status'] ?? null;

            if ($state === 'PUBLISH_COMPLETE') {
                $ids = $status['publicaly_available_post_id'] ?? [];
                $id = is_array($ids) && isset($ids[0]) && is_scalar($ids[0]) ? (string) $ids[0] : $publish;

                return ['id' => $id, 'url' => $id === $publish ? null : 'https://www.tiktok.com/@/video/'.$id];
            }

            if ($state === 'FAILED') {
                throw new VendorUnavailable('TikTok: '.(is_string($status['fail_reason'] ?? null) ? $status['fail_reason'] : 'publishing failed'));
            }

            sleep(3);
        }

        // Still processing: keep the publish id; insights start once TikTok finishes.
        return ['id' => $publish, 'url' => null];
    }

    public function insights(Connection $connection, string $accessToken, array $target, string $externalId): array
    {
        $body = $this->data($this->http($accessToken)->post(self::API.'/video/query/?fields=id,view_count,like_count,comment_count,share_count', ['filters' => ['video_ids' => [$externalId]]]));
        $video = data_get($body, 'videos.0');
        $out = [];

        foreach (['view_count' => 'views', 'like_count' => 'likes', 'comment_count' => 'comments', 'share_count' => 'shares'] as $from => $to) {
            $value = is_array($video) ? ($video[$from] ?? null) : null;
            if (is_int($value)) {
                $out[$to] = $value;
            }
        }

        return $out;
    }

    /** @param  array<string, string>  $params */
    private function token(array $params): TokenSet
    {
        $body = self::body(Http::asForm()->acceptJson()->timeout(15)->post(self::API.'/oauth/token/', [...$params, 'client_key' => $this->clientKey, 'client_secret' => $this->clientSecret]), 'TikTok');
        $access = $body['access_token'] ?? null;

        if (! is_string($access) || $access === '') {
            throw new AccessRevoked('TikTok refused the sign-in.');
        }

        $seconds = is_numeric($body['expires_in'] ?? null) ? (int) $body['expires_in'] : 86400;

        return new TokenSet($access, is_string($body['refresh_token'] ?? null) ? $body['refresh_token'] : ($params['refresh_token'] ?? null), CarbonImmutable::now()->addSeconds($seconds), self::SCOPES);
    }

    /**
     * TikTok answers {data, error: {code}}; "ok" is success.
     *
     * @return array<mixed>
     */
    private function data(Response $response): array
    {
        $body = self::body($response, 'TikTok');
        $code = data_get($body, 'error.code');

        if ($code === 'access_token_invalid' || $code === 'scope_not_authorized') {
            throw new AccessRevoked('TikTok: '.$code);
        }

        if (is_string($code) && $code !== 'ok') {
            throw new VendorUnavailable('TikTok: '.$code);
        }

        $data = $body['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    private function http(string $token): PendingRequest
    {
        return Http::timeout(30)->withToken($token)->acceptJson()->asJson();
    }
}
