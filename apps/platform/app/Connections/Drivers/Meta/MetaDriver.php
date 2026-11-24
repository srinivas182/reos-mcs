<?php

declare(strict_types=1);

namespace App\Connections\Drivers\Meta;

use App\Connections\ConnectedAccount;
use App\Connections\ConnectorDriver;
use App\Connections\Drivers\Social\PostsOnly;
use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Exceptions\VendorRateLimited;
use App\Connections\Exceptions\VendorUnavailable;
use App\Connections\PublishesPosts;
use App\Connections\TokenSet;
use App\Models\Connection;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Instagram and Facebook through Meta's Graph API (B11.1, ADR 0046).
 *
 * - **Sign-in:** Facebook Login; the short-lived user token is swapped for a
 *   long-lived one (about 60 days), which is also kept as the "refresh"
 *   token so REOS can swap it again before it lapses.
 * - **Targets:** each Facebook Page the agent manages, and the Instagram
 *   business or creator account linked to it.
 * - **Publishing:** a Page post (`/feed`, or `/photos` with an image) with
 *   the Page's own token; Instagram in two steps (a media container, then
 *   `media_publish`), waiting for a video to finish processing.
 * - **Insights:** reach and clicks from the Page post's insights, likes and
 *   comments from its summaries; Instagram's media insights.
 */
final readonly class MetaDriver implements ConnectorDriver, PublishesPosts
{
    use PostsOnly;

    public const INTEGRATION = 'meta';

    public const VERSION = 'v21.0';

    public const GRAPH = 'https://graph.facebook.com/'.self::VERSION;

    /** No synced streams: posts go out from REOS, and insights are read per post. */
    public const STREAMS = [];

    /** What the agent agrees to (Connections, screen 170). */
    public const CONSENT = ['can' => ['list_pages', 'publish_posts', 'read_insights'], 'never' => ['post_without_you', 'read_messages']];

    public const SCOPES = ['pages_show_list', 'pages_manage_posts', 'pages_read_engagement', 'read_insights', 'business_management', 'instagram_basic', 'instagram_content_publish', 'instagram_manage_insights'];

    public function __construct(private string $appId, private string $appSecret) {}

    public function integration(): string
    {
        return self::INTEGRATION;
    }

    public static function channels(): array
    {
        return [
            // Instagram posts need a photo or video; captions up to 2,200 characters.
            'instagram' => ['media' => 'required', 'max_text' => 2200],
            'facebook' => ['media' => 'optional', 'max_text' => 63206],
        ];
    }

    public function authorizationUrl(string $state, string $codeChallenge, string $redirectUri): string
    {
        return 'https://www.facebook.com/'.self::VERSION.'/dialog/oauth?'.http_build_query([
            'client_id' => $this->appId, 'redirect_uri' => $redirectUri, 'state' => $state, 'response_type' => 'code',
            'scope' => implode(',', self::SCOPES), 'code_challenge' => $codeChallenge, 'code_challenge_method' => 'S256',
        ]);
    }

    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): TokenSet
    {
        $short = $this->json(Http::timeout(15)->get(self::GRAPH.'/oauth/access_token', [
            'client_id' => $this->appId, 'client_secret' => $this->appSecret, 'redirect_uri' => $redirectUri, 'code' => $code, 'code_verifier' => $codeVerifier,
        ]));
        $token = $short['access_token'] ?? null;

        if (! is_string($token)) {
            throw new AccessRevoked('Meta returned no token.');
        }

        return $this->refresh($token);
    }

    /** Meta has no refresh tokens: a long-lived token is swapped for a fresh one while it's still valid. */
    public function refresh(string $refreshToken): TokenSet
    {
        $body = $this->json(Http::timeout(15)->get(self::GRAPH.'/oauth/access_token', [
            'grant_type' => 'fb_exchange_token', 'client_id' => $this->appId, 'client_secret' => $this->appSecret, 'fb_exchange_token' => $refreshToken,
        ]));
        $token = $body['access_token'] ?? null;

        if (! is_string($token)) {
            throw new AccessRevoked('Meta refused the token.');
        }

        $seconds = is_numeric($body['expires_in'] ?? null) ? (int) $body['expires_in'] : 60 * 86400;

        return new TokenSet($token, $token, CarbonImmutable::now()->addSeconds($seconds), self::SCOPES);
    }

    public function revoke(string $token): void
    {
        Http::timeout(10)->delete(self::GRAPH.'/me/permissions', ['access_token' => $token]);
    }

    public function account(TokenSet $tokens): ConnectedAccount
    {
        $me = $this->json($this->http($tokens->accessToken)->get(self::GRAPH.'/me', ['fields' => 'id,name']));
        $id = $me['id'] ?? null;

        if (! is_string($id) || $id === '') {
            throw new AccessRevoked('Meta returned no user.');
        }

        return new ConnectedAccount($id, is_string($me['name'] ?? null) ? $me['name'] : 'Meta');
    }

    public function requestsPerMinute(): int
    {
        // Graph API app limits are generous; posting is rate-limited per Page and account.
        return 60;
    }

    public function targets(Connection $connection, string $accessToken): array
    {
        $body = $this->json($this->http($accessToken)->get(self::GRAPH.'/me/accounts', ['fields' => 'id,name,instagram_business_account{id,username}', 'limit' => 100]));
        $targets = [];

        foreach (is_array($body['data'] ?? null) ? $body['data'] : [] as $page) {
            if (! is_array($page) || ! is_string($page['id'] ?? null)) {
                continue;
            }

            $targets[] = ['channel' => 'facebook', 'id' => $page['id'], 'name' => is_string($page['name'] ?? null) ? $page['name'] : $page['id']];
            $ig = $page['instagram_business_account'] ?? null;

            if (is_array($ig) && is_string($ig['id'] ?? null)) {
                $targets[] = ['channel' => 'instagram', 'id' => $ig['id'], 'name' => '@'.(is_string($ig['username'] ?? null) ? $ig['username'] : $ig['id'])];
            }
        }

        return $targets;
    }

    public function publish(Connection $connection, string $accessToken, array $target, array $content): array
    {
        return $target['channel'] === 'instagram' ? $this->instagram($accessToken, $target, $content) : $this->facebook($accessToken, $target, $content);
    }

    public function insights(Connection $connection, string $accessToken, array $target, string $externalId): array
    {
        if ($target['channel'] === 'instagram') {
            $body = $this->json($this->http($accessToken)->get(self::GRAPH."/{$externalId}/insights", ['metric' => 'reach,likes,comments,shares,views']));

            return self::metrics($body, ['reach' => 'reach', 'likes' => 'likes', 'comments' => 'comments', 'shares' => 'shares', 'views' => 'views']);
        }

        $page = $this->pageToken($accessToken, $target['id']);
        $summary = $this->json($this->http($page)->get(self::GRAPH."/{$externalId}", ['fields' => 'reactions.summary(true).limit(0),comments.summary(true).limit(0),shares']));
        $insights = $this->json($this->http($page)->get(self::GRAPH."/{$externalId}/insights", ['metric' => 'post_impressions_unique,post_clicks']));
        $metrics = self::metrics($insights, ['post_impressions_unique' => 'reach', 'post_clicks' => 'clicks']);
        $likes = data_get($summary, 'reactions.summary.total_count');
        $comments = data_get($summary, 'comments.summary.total_count');
        $shares = data_get($summary, 'shares.count');

        return [...$metrics, ...(is_int($likes) ? ['likes' => $likes] : []), ...(is_int($comments) ? ['comments' => $comments] : []), ...(is_int($shares) ? ['shares' => $shares] : [])];
    }

    /**
     * @param  array{channel: string, id: string, name: string}  $target
     * @param  array{text: string, link: string|null, media: list<array{url: string, kind: string}>}  $content
     * @return array{id: string, url: string|null}
     */
    private function facebook(string $accessToken, array $target, array $content): array
    {
        $page = $this->pageToken($accessToken, $target['id']);
        $image = array_values(array_filter($content['media'], static fn (array $m): bool => $m['kind'] === 'image'))[0] ?? null;
        $message = $content['text'].($content['link'] !== null && $image !== null ? "\n\n".$content['link'] : '');
        $body = $image !== null
            ? $this->json($this->http($page)->asForm()->post(self::GRAPH."/{$target['id']}/photos", ['url' => $image['url'], 'caption' => $message]))
            : $this->json($this->http($page)->asForm()->post(self::GRAPH."/{$target['id']}/feed", array_filter(['message' => $message, 'link' => $content['link']], static fn ($v): bool => $v !== null)));
        $id = $body['post_id'] ?? $body['id'] ?? null;

        if (! is_string($id)) {
            throw new VendorUnavailable('Facebook returned no post id.');
        }

        return ['id' => $id, 'url' => 'https://www.facebook.com/'.$id];
    }

    /**
     * @param  array{channel: string, id: string, name: string}  $target
     * @param  array{text: string, link: string|null, media: list<array{url: string, kind: string}>}  $content
     * @return array{id: string, url: string|null}
     */
    private function instagram(string $accessToken, array $target, array $content): array
    {
        $media = $content['media'][0] ?? throw new VendorUnavailable('Instagram needs a photo or video.');
        $caption = $content['text'].($content['link'] !== null ? "\n\n".$content['link'] : '');
        $params = $media['kind'] === 'video' ? ['media_type' => 'REELS', 'video_url' => $media['url'], 'caption' => $caption] : ['image_url' => $media['url'], 'caption' => $caption];
        $container = $this->json($this->http($accessToken)->asForm()->post(self::GRAPH."/{$target['id']}/media", $params))['id'] ?? null;

        if (! is_string($container)) {
            throw new VendorUnavailable('Instagram returned no media container.');
        }

        // A video is processed before it can be published.
        for ($i = 0; $media['kind'] === 'video' && $i < 20; $i++) {
            $status = $this->json($this->http($accessToken)->get(self::GRAPH."/{$container}", ['fields' => 'status_code']))['status_code'] ?? null;

            if ($status === 'FINISHED') {
                break;
            }

            if ($status === 'ERROR' || $status === 'EXPIRED') {
                throw new VendorUnavailable('Instagram could not process the video.');
            }

            sleep(3);
        }

        $id = $this->json($this->http($accessToken)->asForm()->post(self::GRAPH."/{$target['id']}/media_publish", ['creation_id' => $container]))['id'] ?? null;

        if (! is_string($id)) {
            throw new VendorUnavailable('Instagram returned no media id.');
        }

        $link = $this->json($this->http($accessToken)->get(self::GRAPH."/{$id}", ['fields' => 'permalink']))['permalink'] ?? null;

        return ['id' => $id, 'url' => is_string($link) ? $link : null];
    }

    /** A Page's own token, from the agent's token (never stored). */
    private function pageToken(string $accessToken, string $pageId): string
    {
        $token = $this->json($this->http($accessToken)->get(self::GRAPH."/{$pageId}", ['fields' => 'access_token']))['access_token'] ?? null;

        return is_string($token) ? $token : throw new AccessRevoked('No access to the Page.');
    }

    /**
     * Graph insights ({data: [{name, values: [{value}]}]}) as REOS's metric names.
     *
     * @param  array<mixed>  $body
     * @param  array<string, string>  $names
     * @return array<string, int>
     */
    private static function metrics(array $body, array $names): array
    {
        $metrics = [];

        foreach (is_array($body['data'] ?? null) ? $body['data'] : [] as $row) {
            $name = is_array($row) ? ($row['name'] ?? null) : null;
            $value = is_array($row) ? data_get($row, 'values.0.value') : null;

            if (is_string($name) && isset($names[$name]) && is_numeric($value)) {
                $metrics[$names[$name]] = (int) $value;
            }
        }

        return $metrics;
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function http(string $token): PendingRequest
    {
        return Http::timeout(30)->withToken($token)->acceptJson();
    }

    /** @return array<mixed> */
    private function json(Response $response): array
    {
        $code = $response->json('error.code');

        if ($response->status() === 429 || in_array($code, [4, 17, 32, 613], true)) {
            throw new VendorRateLimited(60);
        }

        if ($response->status() === 401 || $code === 190) {
            throw new AccessRevoked('Meta: '.self::text($response->json('error.message')));
        }

        if ($response->failed()) {
            throw new VendorUnavailable('Meta: '.mb_substr(self::text($response->json('error.message', $response->body())), 0, 200));
        }

        $body = $response->json();

        return is_array($body) ? $body : [];
    }
}
