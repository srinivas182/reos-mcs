<?php

declare(strict_types=1);

namespace App\Connections\Drivers\YouTube;

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
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * YouTube Shorts (B11.3, ADR 0046) through the YouTube Data API v3.
 *
 * - **Targets:** the agent's own channel.
 * - **Publishing:** a resumable upload of the post's vertical video, titled
 *   from the text's first line with #Shorts; the text is the description.
 *   Until the API project passes Google's audit, YouTube keeps uploads
 *   private on its side.
 * - **Insights:** views, likes and comments from the video's statistics.
 */
final readonly class YouTubeDriver implements ConnectorDriver, PublishesPosts
{
    use PostsOnly;
    use SocialHttp;

    public const INTEGRATION = 'youtube';

    public const STREAMS = [];

    public const CONSENT = ['can' => ['list_pages', 'publish_posts', 'read_insights'], 'never' => ['post_without_you', 'read_messages']];

    public const SCOPES = ['openid', 'email', 'https://www.googleapis.com/auth/youtube.upload', 'https://www.googleapis.com/auth/youtube.readonly'];

    /** "People & Blogs". */
    private const CATEGORY = '22';

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
        return ['youtube' => ['media' => 'video', 'max_text' => 5000]];
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
        $me = self::body(Http::timeout(15)->withToken($tokens->accessToken)->acceptJson()->get('https://openidconnect.googleapis.com/v1/userinfo'), 'YouTube');
        $sub = $me['sub'] ?? null;

        if (! is_string($sub) || $sub === '') {
            throw new AccessRevoked('Google returned no account.');
        }

        return new ConnectedAccount($sub, is_string($me['email'] ?? null) ? $me['email'] : 'YouTube');
    }

    public function requestsPerMinute(): int
    {
        // Quota is in units a day (an upload costs 1,600); requests are rarely the limit.
        return 60;
    }

    public function targets(Connection $connection, string $accessToken): array
    {
        $body = self::body($this->http($accessToken)->get('https://www.googleapis.com/youtube/v3/channels', ['part' => 'snippet', 'mine' => 'true']), 'YouTube');
        $targets = [];

        foreach (is_array($body['items'] ?? null) ? $body['items'] : [] as $item) {
            if (is_array($item) && is_string($item['id'] ?? null)) {
                $title = data_get($item, 'snippet.title');
                $targets[] = ['channel' => 'youtube', 'id' => $item['id'], 'name' => is_string($title) ? $title : $item['id']];
            }
        }

        return $targets;
    }

    public function publish(Connection $connection, string $accessToken, array $target, array $content): array
    {
        $video = array_values(array_filter($content['media'], static fn (array $m): bool => $m['kind'] === 'video'))[0] ?? throw new VendorUnavailable('YouTube Shorts needs a video.');
        $first = trim((string) strtok($content['text'], "\n"));
        $title = mb_substr($first !== '' ? $first : 'New video', 0, 90).' #Shorts';
        $start = $this->http($accessToken)->withHeaders(['X-Upload-Content-Type' => 'video/*'])->post('https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status', [
            'snippet' => ['title' => $title, 'description' => $content['text'].($content['link'] !== null ? "\n\n".$content['link'] : ''), 'categoryId' => self::CATEGORY],
            'status' => ['privacyStatus' => 'public', 'selfDeclaredMadeForKids' => false],
        ]);
        self::body($start, 'YouTube');
        $session = $start->header('Location');

        if ($session === '') {
            throw new VendorUnavailable('YouTube returned no upload session.');
        }

        $file = tmpfile();

        if ($file === false) {
            throw new VendorUnavailable('No room to stage the video.');
        }

        // The video streams from REOS's storage to YouTube without being held in memory.
        $download = Http::timeout(300)->withOptions(['sink' => $file])->get($video['url']);

        if ($download->failed()) {
            throw new VendorUnavailable('The video could not be read.');
        }

        rewind($file);
        $uploaded = self::body(Http::timeout(600)->withToken($accessToken)->withBody(Utils::streamFor($file), 'video/*')->put($session), 'YouTube');
        $id = $uploaded['id'] ?? null;

        if (! is_string($id)) {
            throw new VendorUnavailable('YouTube returned no video id.');
        }

        return ['id' => $id, 'url' => 'https://youtube.com/shorts/'.$id];
    }

    public function insights(Connection $connection, string $accessToken, array $target, string $externalId): array
    {
        $body = self::body($this->http($accessToken)->get('https://www.googleapis.com/youtube/v3/videos', ['part' => 'statistics', 'id' => $externalId]), 'YouTube');
        $stats = data_get($body, 'items.0.statistics');
        $out = [];

        foreach (['viewCount' => 'views', 'likeCount' => 'likes', 'commentCount' => 'comments'] as $from => $to) {
            $value = is_array($stats) ? ($stats[$from] ?? null) : null;
            if (is_numeric($value)) {
                $out[$to] = (int) $value;
            }
        }

        return $out;
    }

    private function http(string $token): PendingRequest
    {
        return Http::timeout(30)->withToken($token)->acceptJson();
    }
}
