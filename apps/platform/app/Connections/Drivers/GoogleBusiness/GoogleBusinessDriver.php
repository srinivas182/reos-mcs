<?php

declare(strict_types=1);

namespace App\Connections\Drivers\GoogleBusiness;

use App\Connections\ConnectedAccount;
use App\Connections\ConnectorDriver;
use App\Connections\Drivers\Social\PostsOnly;
use App\Connections\Drivers\Social\SocialHttp;
use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Exceptions\VendorUnavailable;
use App\Connections\OAuth2Client;
use App\Connections\PublishesPosts;
use App\Connections\TokenSet;
use App\Models\Connection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Google Business Profile (B11.2, ADR 0046): local posts on the agent's
 * Google listing.
 *
 * - **Targets:** each location of each account the agent manages (Account
 *   Management and Business Information APIs).
 * - **Posts:** "What's new" local posts (v4 `localPosts`), up to 1,500
 *   characters, with one photo and a Learn more button for a link.
 * - **Insights:** views and button clicks, where Google still reports them
 *   for local posts.
 */
final readonly class GoogleBusinessDriver implements ConnectorDriver, PublishesPosts
{
    use PostsOnly;
    use SocialHttp;

    public const INTEGRATION = 'google-business-profile';

    public const STREAMS = [];

    public const CONSENT = ['can' => ['list_pages', 'publish_posts', 'read_insights'], 'never' => ['post_without_you', 'read_messages']];

    public const SCOPES = ['openid', 'email', 'https://www.googleapis.com/auth/business.manage'];

    private OAuth2Client $oauth;

    public function __construct(string $clientId, string $clientSecret)
    {
        $this->oauth = new OAuth2Client($clientId, $clientSecret, 'https://accounts.google.com/o/oauth2/v2/auth', 'https://oauth2.googleapis.com/token', 'https://oauth2.googleapis.com/revoke', self::SCOPES, ['access_type' => 'offline', 'prompt' => 'consent', 'include_granted_scopes' => 'true']);
    }

    public function integration(): string
    {
        return self::INTEGRATION;
    }

    public static function channels(): array
    {
        return ['google_business' => ['media' => 'optional', 'max_text' => 1500]];
    }

    public function authorizationUrl(string $state, string $codeChallenge, string $redirectUri): string
    {
        return $this->oauth->authorizationUrl($state, $codeChallenge, $redirectUri);
    }

    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): TokenSet
    {
        return $this->oauth->exchangeCode($code, $codeVerifier, $redirectUri);
    }

    public function refresh(string $refreshToken): TokenSet
    {
        return $this->oauth->refresh($refreshToken);
    }

    public function revoke(string $token): void
    {
        $this->oauth->revoke($token);
    }

    public function account(TokenSet $tokens): ConnectedAccount
    {
        $me = self::body(Http::timeout(15)->withToken($tokens->accessToken)->acceptJson()->get('https://openidconnect.googleapis.com/v1/userinfo'), 'Google');
        $sub = $me['sub'] ?? null;

        if (! is_string($sub) || $sub === '') {
            throw new AccessRevoked('Google returned no account.');
        }

        return new ConnectedAccount($sub, is_string($me['email'] ?? null) ? $me['email'] : 'Google Business Profile');
    }

    public function requestsPerMinute(): int
    {
        // Business Profile APIs allow 300 requests a minute per project.
        return 120;
    }

    public function targets(Connection $connection, string $accessToken): array
    {
        $targets = [];
        $accounts = self::body($this->http($accessToken)->get('https://mybusinessaccountmanagement.googleapis.com/v1/accounts'), 'Google');

        foreach (is_array($accounts['accounts'] ?? null) ? $accounts['accounts'] : [] as $account) {
            $name = is_array($account) ? ($account['name'] ?? null) : null;
            if (! is_string($name)) {
                continue;
            }

            $locations = self::body($this->http($accessToken)->get("https://mybusinessbusinessinformation.googleapis.com/v1/{$name}/locations", ['readMask' => 'name,title', 'pageSize' => 100]), 'Google');
            foreach (is_array($locations['locations'] ?? null) ? $locations['locations'] : [] as $location) {
                if (is_array($location) && is_string($location['name'] ?? null)) {
                    $targets[] = ['channel' => 'google_business', 'id' => "{$name}/{$location['name']}", 'name' => is_string($location['title'] ?? null) ? $location['title'] : $location['name']];
                }
            }
        }

        return $targets;
    }

    public function publish(Connection $connection, string $accessToken, array $target, array $content): array
    {
        $image = array_values(array_filter($content['media'], static fn (array $m): bool => $m['kind'] === 'image'))[0] ?? null;
        $post = array_filter([
            'languageCode' => app()->getLocale() === 'es' ? 'es-US' : 'en-US',
            'summary' => mb_substr($content['text'], 0, 1500),
            'topicType' => 'STANDARD',
            'callToAction' => $content['link'] === null ? null : ['actionType' => 'LEARN_MORE', 'url' => $content['link']],
            'media' => $image === null ? null : [['mediaFormat' => 'PHOTO', 'sourceUrl' => $image['url']]],
        ], static fn ($v): bool => $v !== null);
        $body = self::body($this->http($accessToken)->post("https://mybusiness.googleapis.com/v4/{$target['id']}/localPosts", $post), 'Google');
        $name = $body['name'] ?? null;

        if (! is_string($name)) {
            throw new VendorUnavailable('Google returned no post.');
        }

        return ['id' => $name, 'url' => is_string($body['searchUrl'] ?? null) ? $body['searchUrl'] : null];
    }

    public function insights(Connection $connection, string $accessToken, array $target, string $externalId): array
    {
        try {
            $body = self::body($this->http($accessToken)->post("https://mybusiness.googleapis.com/v4/{$target['id']}/localPosts:reportInsights", [
                'localPostNames' => [$externalId],
                'basicRequest' => ['metricRequests' => [['metric' => 'LOCAL_POST_VIEWS_SEARCH'], ['metric' => 'LOCAL_POST_ACTIONS_CALL_TO_ACTION']]],
            ]), 'Google');
        } catch (AccessRevoked $e) {
            throw $e;
        } catch (Throwable) {
            // Google has been retiring local-post insights; no numbers is not an error.
            return [];
        }

        $out = [];
        foreach ((array) data_get($body, 'localPostMetrics.0.metricValues', []) as $value) {
            $metric = is_array($value) ? ($value['metric'] ?? null) : null;
            $count = is_array($value) ? data_get($value, 'totalValue.value') : null;
            if (is_numeric($count) && $metric === 'LOCAL_POST_VIEWS_SEARCH') {
                $out['views'] = (int) $count;
            } elseif (is_numeric($count) && $metric === 'LOCAL_POST_ACTIONS_CALL_TO_ACTION') {
                $out['clicks'] = (int) $count;
            }
        }

        return $out;
    }

    private function http(string $token): PendingRequest
    {
        return Http::timeout(30)->withToken($token)->acceptJson();
    }
}
