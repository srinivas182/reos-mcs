<?php

declare(strict_types=1);

namespace App\Connections\Drivers\GoogleCalendar;

use App\Connections\ConnectedAccount;
use App\Connections\ConnectorDriver;
use App\Connections\Exceptions\CursorExpired;
use App\Connections\Exceptions\VendorUnavailable;
use App\Connections\OAuth2Client;
use App\Connections\Stores\CalendarEventStore;
use App\Connections\SubscribesToChanges;
use App\Connections\SyncPage;
use App\Connections\TokenSet;
use App\Connections\WebhookDelivery;
use App\Models\Connection;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Google Calendar, read-only (B3.1 sample connector; two-way in B4.1), on the
 * `google-workspace` client keys from Settings › Integrations.
 *
 * - Sign-in: Google OAuth with PKCE, offline access, `calendar.readonly`.
 * - Sync: the primary calendar's events. The first sync pages from the
 *   history start; Google then hands back a `nextSyncToken` for changes only.
 *   A 410 means the token expired and the stream starts again.
 * - Webhooks: Google push notifications, verified by the channel token REOS
 *   set when it subscribed (`GoogleCalendarChannels`).
 *
 * @see https://developers.google.com/calendar/api/guides/sync
 * @see https://developers.google.com/calendar/api/guides/push
 */
final readonly class GoogleCalendarDriver implements ConnectorDriver, SubscribesToChanges
{
    public const INTEGRATION = 'google-workspace';

    public const SCOPES = ['openid', 'email', 'https://www.googleapis.com/auth/calendar.readonly'];

    private const API = 'https://www.googleapis.com/calendar/v3';

    private OAuth2Client $oauth;

    public function __construct(string $clientId, string $clientSecret)
    {
        $this->oauth = new OAuth2Client(
            $clientId,
            $clientSecret,
            'https://accounts.google.com/o/oauth2/v2/auth',
            'https://oauth2.googleapis.com/token',
            'https://oauth2.googleapis.com/revoke',
            self::SCOPES,
            // A refresh token every time, and keep scopes granted earlier (Gmail in B4.1).
            ['access_type' => 'offline', 'prompt' => 'consent', 'include_granted_scopes' => 'true'],
        );
    }

    public function integration(): string
    {
        return self::INTEGRATION;
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
        $response = $this->get('https://openidconnect.googleapis.com/v1/userinfo', $tokens->accessToken, []);
        $subject = $response->json('sub');
        $email = $response->json('email');

        if (! is_string($subject) || $subject === '') {
            throw new VendorUnavailable('Google returned no account id.');
        }

        return new ConnectedAccount($subject, is_string($email) ? $email : null);
    }

    public function streams(): array
    {
        return ['events' => CalendarEventStore::class];
    }

    public function fetch(Connection $connection, string $accessToken, string $stream, ?string $cursor, ?CarbonImmutable $since): SyncPage
    {
        $state = $cursor === null ? [] : (array) json_decode($cursor, true);
        $query = ['singleEvents' => 'true', 'showDeleted' => 'true', 'maxResults' => '250'];

        if (is_string($state['page'] ?? null)) {
            $query['pageToken'] = $state['page'];
        }
        if (is_string($state['sync'] ?? null)) {
            // Changes since the last sync; Google doesn't allow timeMin with a sync token.
            $query['syncToken'] = $state['sync'];
        } elseif (is_string($state['since'] ?? null)) {
            $query['timeMin'] = $state['since'];
        } elseif ($since !== null) {
            $query['timeMin'] = $since->toRfc3339String();
        }

        $response = $this->get(self::API.'/calendars/primary/events', $accessToken, $query);

        $records = [];
        $removed = [];

        foreach ((array) $response->json('items', []) as $item) {
            if (! is_array($item) || ! is_string($item['id'] ?? null)) {
                continue;
            }

            if (($item['status'] ?? null) === 'cancelled') {
                $removed[] = $item['id'];

                continue;
            }

            $record = self::record($item);

            if ($record !== null) {
                $records[] = $record;
            }
        }

        $nextPage = $response->json('nextPageToken');
        $nextSync = $response->json('nextSyncToken');

        if (is_string($nextPage) && $nextPage !== '') {
            // Keep the window (or sync token) so the next page asks the same question.
            return new SyncPage($records, $removed, json_encode([
                'page' => $nextPage,
                'sync' => $state['sync'] ?? null,
                'since' => $query['timeMin'] ?? null,
            ], JSON_THROW_ON_ERROR), false);
        }

        return new SyncPage($records, $removed, is_string($nextSync) ? json_encode(['sync' => $nextSync], JSON_THROW_ON_ERROR) : null, true);
    }

    public function requestsPerMinute(): int
    {
        // Well under Google Calendar's default per-project quota.
        return 300;
    }

    public function verifyWebhook(Request $request): ?WebhookDelivery
    {
        $channel = $request->header('X-Goog-Channel-ID');
        $token = $request->header('X-Goog-Channel-Token');
        $number = $request->header('X-Goog-Message-Number');
        $state = $request->header('X-Goog-Resource-State');

        if (! is_string($channel) || ! is_string($token) || ! is_string($number) || ! is_string($state)) {
            return null;
        }

        $connectionId = GoogleCalendarChannels::connectionFor($channel, $token);

        if ($connectionId === null) {
            return null;
        }

        return new WebhookDelivery("{$channel}:{$number}", [$connectionId], $state !== 'sync');
    }

    public function subscribe(Connection $connection, string $accessToken, string $webhookUrl): array
    {
        $channel = GoogleCalendarChannels::newChannelId($connection->id);

        try {
            $response = Http::withToken($accessToken)->acceptJson()->timeout(20)->post(self::API.'/calendars/primary/events/watch', [
                'id' => $channel,
                'type' => 'web_hook',
                'address' => $webhookUrl,
                'token' => GoogleCalendarChannels::token($channel),
            ]);
        } catch (ConnectionException $e) {
            throw new VendorUnavailable('Google unreachable.', previous: $e);
        }

        OAuth2Client::raiseFor($response);
        $expiration = $response->json('expiration');

        return [
            'channel_id' => $channel,
            'resource_id' => $response->json('resourceId'),
            // Google sends milliseconds; channels last about a week and are renewed (B4.1).
            'channel_expires_at' => is_numeric($expiration) ? CarbonImmutable::createFromTimestampMs((int) $expiration)->toIso8601String() : null,
        ];
    }

    /**
     * One event as REOS stores it, or null when Google sends something without times.
     *
     * @param  array<array-key, mixed>  $item
     * @return array<string, mixed>|null
     */
    private static function record(array $item): ?array
    {
        $start = is_array($item['start'] ?? null) ? $item['start'] : [];
        $end = is_array($item['end'] ?? null) ? $item['end'] : [];
        $allDay = isset($start['date']) && ! isset($start['dateTime']);
        $startsAt = $start['dateTime'] ?? $start['date'] ?? null;
        $endsAt = $end['dateTime'] ?? $end['date'] ?? $startsAt;

        if (! is_string($startsAt) || ! is_string($endsAt)) {
            return null;
        }

        return [
            'external_id' => is_string($item['id'] ?? null) ? $item['id'] : '',
            'calendar_id' => 'primary',
            'title' => is_string($item['summary'] ?? null) ? mb_substr($item['summary'], 0, 255) : null,
            'location' => is_string($item['location'] ?? null) ? $item['location'] : null,
            'starts_at' => CarbonImmutable::parse($startsAt)->utc(),
            'ends_at' => CarbonImmutable::parse($endsAt)->utc(),
            'all_day' => $allDay,
            'status' => is_string($item['status'] ?? null) ? $item['status'] : 'confirmed',
            'html_link' => is_string($item['htmlLink'] ?? null) ? mb_substr($item['htmlLink'], 0, 2048) : null,
            'remote_updated_at' => is_string($item['updated'] ?? null) ? CarbonImmutable::parse($item['updated'])->utc() : null,
        ];
    }

    /** @param  array<string, string>  $query */
    private function get(string $url, string $accessToken, array $query): Response
    {
        try {
            $response = Http::withToken($accessToken)->acceptJson()->timeout(20)->get($url, $query);
        } catch (ConnectionException $e) {
            throw new VendorUnavailable('Google unreachable.', previous: $e);
        }

        if ($response->status() === 410) {
            throw new CursorExpired('Google sync token expired.');
        }

        OAuth2Client::raiseFor($response);

        return $response;
    }
}
