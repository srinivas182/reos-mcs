<?php

declare(strict_types=1);

namespace App\Connections\Drivers\LinkedIn;

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
 * LinkedIn (B11.2, ADR 0046), on the versioned REST API.
 *
 * - **Member posts** (self-serve, "Share on LinkedIn"): the author is the
 *   member from OpenID Connect `userinfo`.
 * - **Company pages** need the Community Management API (beta until
 *   LinkedIn approves it): the pages the member administers are listed
 *   when the app has that access, and skipped quietly when it doesn't.
 * - **Images** are uploaded first (`images?action=initializeUpload`, then
 *   a PUT of the bytes); a link becomes an article card.
 * - **Insights:** likes and comments from `socialActions`; page posts add
 *   impressions and clicks from the share statistics.
 */
final readonly class LinkedInDriver implements ConnectorDriver, PublishesPosts
{
    use PostsOnly;
    use SocialHttp;

    public const INTEGRATION = 'linkedin';

    public const API = 'https://api.linkedin.com/rest';

    /** The LinkedIn-Version header (yyyymm); LinkedIn keeps each version for a year. */
    public const VERSION = '202411';

    public const STREAMS = [];

    public const CONSENT = ['can' => ['list_pages', 'publish_posts', 'read_insights'], 'never' => ['post_without_you', 'read_messages']];

    public const SCOPES = ['openid', 'profile', 'w_member_social', 'r_organization_social', 'w_organization_social', 'rw_organization_admin'];

    private OAuth2Client $oauth;

    public function __construct(string $clientId, string $clientSecret)
    {
        $this->oauth = new OAuth2Client($clientId, $clientSecret, 'https://www.linkedin.com/oauth/v2/authorization', 'https://www.linkedin.com/oauth/v2/accessToken', 'https://www.linkedin.com/oauth/v2/revoke', self::SCOPES);
    }

    public function integration(): string
    {
        return self::INTEGRATION;
    }

    public static function channels(): array
    {
        return ['linkedin' => ['media' => 'optional', 'max_text' => 3000]];
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
        $me = self::body(Http::timeout(15)->withToken($tokens->accessToken)->acceptJson()->get('https://api.linkedin.com/v2/userinfo'), 'LinkedIn');
        $sub = $me['sub'] ?? null;

        if (! is_string($sub) || $sub === '') {
            throw new AccessRevoked('LinkedIn returned no member.');
        }

        return new ConnectedAccount($sub, is_string($me['name'] ?? null) ? $me['name'] : 'LinkedIn');
    }

    public function requestsPerMinute(): int
    {
        return 30;
    }

    public function targets(Connection $connection, string $accessToken): array
    {
        $targets = [['channel' => 'linkedin', 'id' => 'urn:li:person:'.$connection->external_account_id, 'name' => $connection->account_label ?? 'LinkedIn']];

        // Company pages need Community Management API access (beta).
        try {
            $acls = self::body($this->http($accessToken)->get(self::API.'/organizationAcls', ['q' => 'roleAssignee', 'role' => 'ADMINISTRATOR', 'state' => 'APPROVED']), 'LinkedIn');
            foreach (is_array($acls['elements'] ?? null) ? $acls['elements'] : [] as $acl) {
                $org = is_array($acl) ? ($acl['organization'] ?? null) : null;
                if (is_string($org)) {
                    $id = substr($org, strrpos($org, ':') + 1);
                    $info = self::body($this->http($accessToken)->get(self::API."/organizations/{$id}"), 'LinkedIn');
                    $targets[] = ['channel' => 'linkedin', 'id' => $org, 'name' => is_string($info['localizedName'] ?? null) ? $info['localizedName'] : $org];
                }
            }
        } catch (AccessRevoked $e) {
            throw $e;
        } catch (Throwable) {
            // No page access yet: member posts only.
        }

        return $targets;
    }

    public function publish(Connection $connection, string $accessToken, array $target, array $content): array
    {
        $post = [
            'author' => $target['id'], 'commentary' => self::escape($content['text']), 'visibility' => 'PUBLIC',
            'distribution' => ['feedDistribution' => 'MAIN_FEED', 'targetEntities' => [], 'thirdPartyDistributionChannels' => []],
            'lifecycleState' => 'PUBLISHED', 'isReshareDisabledByAuthor' => false,
        ];
        $image = array_values(array_filter($content['media'], static fn (array $m): bool => $m['kind'] === 'image'))[0] ?? null;

        if ($image !== null) {
            $post['content'] = ['media' => ['id' => $this->upload($accessToken, $target['id'], $image['url'])]];
        } elseif ($content['link'] !== null) {
            $post['content'] = ['article' => ['source' => $content['link'], 'title' => mb_substr(strtok($content['text'], "\n") ?: $content['link'], 0, 200)]];
        }

        $response = $this->http($accessToken)->post(self::API.'/posts', $post);
        self::body($response, 'LinkedIn');
        $urn = $response->header('x-restli-id');

        if ($urn === '') {
            throw new VendorUnavailable('LinkedIn returned no post id.');
        }

        return ['id' => $urn, 'url' => 'https://www.linkedin.com/feed/update/'.$urn];
    }

    public function insights(Connection $connection, string $accessToken, array $target, string $externalId): array
    {
        $actions = self::body($this->http($accessToken)->get(self::API.'/socialActions/'.rawurlencode($externalId)), 'LinkedIn');
        $out = [];
        $likes = data_get($actions, 'likesSummary.totalLikes');
        $comments = data_get($actions, 'commentsSummary.aggregatedTotalComments');

        if (is_int($likes)) {
            $out['likes'] = $likes;
        }

        if (is_int($comments)) {
            $out['comments'] = $comments;
        }

        if (str_starts_with($target['id'], 'urn:li:organization:')) {
            try {
                $stats = self::body($this->http($accessToken)->get(self::API.'/organizationalEntityShareStatistics', ['q' => 'organizationalEntity', 'organizationalEntity' => $target['id'], 'shares' => 'List('.rawurlencode($externalId).')']), 'LinkedIn');
                $totals = data_get($stats, 'elements.0.totalShareStatistics');
                foreach (['impressionCount' => 'reach', 'clickCount' => 'clicks', 'shareCount' => 'shares'] as $from => $to) {
                    $value = is_array($totals) ? ($totals[$from] ?? null) : null;
                    if (is_int($value)) {
                        $out[$to] = $value;
                    }
                }
            } catch (AccessRevoked $e) {
                throw $e;
            } catch (Throwable) {
                // Page statistics need Community Management access.
            }
        }

        return $out;
    }

    /** Upload an image for a post; returns its image URN. */
    private function upload(string $accessToken, string $owner, string $url): string
    {
        $init = self::body($this->http($accessToken)->post(self::API.'/images?action=initializeUpload', ['initializeUploadRequest' => ['owner' => $owner]]), 'LinkedIn');
        $uploadUrl = data_get($init, 'value.uploadUrl');
        $image = data_get($init, 'value.image');

        if (! is_string($uploadUrl) || ! is_string($image)) {
            throw new VendorUnavailable('LinkedIn returned no upload address.');
        }

        $bytes = Http::timeout(60)->get($url);

        if ($bytes->failed()) {
            throw new VendorUnavailable('The image could not be read.');
        }

        self::body(Http::timeout(60)->withToken($accessToken)->withBody($bytes->body(), (string) ($bytes->header('Content-Type') ?: 'image/jpeg'))->put($uploadUrl), 'LinkedIn');

        return $image;
    }

    /** LinkedIn's "little text" format reserves some characters; escape them so the text shows as typed. */
    private static function escape(string $text): string
    {
        return (string) preg_replace('/([\\\\|{}@\[\]()<>#*_~])/', '\\\\$1', $text);
    }

    private function http(string $token): PendingRequest
    {
        return Http::timeout(30)->withToken($token)->acceptJson()->withHeaders(['LinkedIn-Version' => self::VERSION, 'X-Restli-Protocol-Version' => '2.0.0']);
    }
}
