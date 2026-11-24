<?php

declare(strict_types=1);

namespace App\Social;

use App\Authorization\Role;
use App\Enums\LicenceType;
use App\Enums\OrganisationType;
use App\Jobs\PublishSocialTarget;
use App\Mls\ListingPosts;
use App\Models\Connection;
use App\Models\Licence;
use App\Models\ListingPost;
use App\Models\MlsListing;
use App\Models\SocialPost;
use App\Models\User;
use App\Notifications\PostApprovalRequested;
use App\Services\Authorization\ActiveRole;
use App\Services\Authorization\Memberships;
use App\Services\States\PersonStates;
use App\Studio\AdvertisingPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The posting engine (B11, ADR 0046; agent screens 108 and 169). One post,
 * one or more places (a Facebook Page, an Instagram account…), each with
 * its own status, retries and insights:
 *
 * - **Attribution** on every post: the agent and their broker (TREC 22 TAC
 *   §535.155), and, for a listing post, the MLS's line (B10.2).
 * - **Checks** before anything is scheduled: the broker's name, each
 *   channel's limits (Instagram needs a photo or video), and no title a
 *   sales agent may not use. A post that fails can't be scheduled.
 * - **Now, or a time:** due posts go out every minute; each place is a job
 *   that retries.
 *
 * @phpstan-import-type Issue from AdvertisingPolicy
 *
 * @phpstan-type Check array{code: string, ok: bool, detail: string|null}
 * @phpstan-type TargetRow array{id: int, integration: string, channel: string, target_id: string, name: string, status: string, error: string|null, url: string|null, published_at: string|null, insights: array<string, int>}
 * @phpstan-type PostRow array{id: string, body: string, media: list<array{url: string, kind: string}>, link: string|null, attribution: string, listing: string|null, status: string, scheduled_at: string|null, published_at: string|null, created_at: string|null, targets: list<TargetRow>, totals: array<string, int>, approval_note: string|null}
 * @phpstan-type Pick array{connection: int, channel: string, id: string}
 */
final readonly class SocialPosts
{
    public const MEDIA_DISK = 'content';

    /** Media links handed to the channels stay valid this long. */
    public const MEDIA_HOURS = 48;

    public function __construct(
        private SocialAccounts $accounts,
        private ListingPosts $listingPosts,
        private ActiveRole $roles,
        private PersonStates $states,
        private AdvertisingPolicy $policy,
        private Memberships $memberships,
    ) {}

    /** @return array{stats: array{posts_this_month: int, reach: int, engagement: int, clicks: int}, scheduled: list<PostRow>, posted: list<PostRow>, drafts: list<PostRow>} */
    public function list(User $user): array
    {
        $posts = SocialPost::query()->where('user_id', $user->id)->with('targets')->latest('id')->limit(200)->get();
        $rows = array_values($posts->map(fn (SocialPost $p): array => $this->row($p))->all());
        $month = CarbonImmutable::now()->startOfMonth();
        $thisMonth = array_filter($rows, static fn (array $r): bool => $r['published_at'] !== null && CarbonImmutable::parse($r['published_at'])->gte($month));
        $sum = static fn (string $key): int => array_sum(array_map(static fn (array $r): int => $r['totals'][$key] ?? 0, $thisMonth));

        return [
            'stats' => ['posts_this_month' => count($thisMonth), 'reach' => $sum('reach'), 'engagement' => $sum('likes') + $sum('comments') + $sum('shares'), 'clicks' => $sum('clicks')],
            // Waiting for the broker (B12.1) shows with the scheduled posts.
            'scheduled' => array_values(array_filter($rows, static fn (array $r): bool => in_array($r['status'], ['approval', 'scheduled', 'publishing'], true))),
            'posted' => array_values(array_filter($rows, static fn (array $r): bool => in_array($r['status'], ['published', 'partly', 'failed'], true))),
            'drafts' => array_values(array_filter($rows, static fn (array $r): bool => $r['status'] === 'draft')),
        ];
    }

    /** @return PostRow */
    public function show(User $user, string $uuid): array
    {
        return $this->row($this->find($user, $uuid));
    }

    /**
     * A new post (or a draft of one) from text and media, or from a listing post.
     *
     * @param  list<string>  $media  media URLs (a listing's MLS photos) or uploaded paths
     * @param  list<Pick>  $picks
     * @return PostRow
     */
    public function create(User $user, string $body, array $media, ?string $link, array $picks, string $when, ?string $at, ?string $listing): array
    {
        $post = new SocialPost(['uuid' => (string) Str::uuid7(), 'user_id' => $user->id]);

        return $this->fill($user, $post, $body, $media, $link, $picks, $when, $at, $listing);
    }

    /**
     * Change a draft or a scheduled post.
     *
     * @param  list<string>  $media
     * @param  list<Pick>  $picks
     * @return PostRow
     */
    public function update(User $user, string $uuid, string $body, array $media, ?string $link, array $picks, string $when, ?string $at): array
    {
        $post = $this->find($user, $uuid);

        if (! in_array($post->status, ['draft', 'approval', 'scheduled'], true)) {
            throw ValidationException::withMessages(['post' => self::line('social.errors.sent')]);
        }

        $listing = $post->listing_post_id === null ? null : ListingPost::query()->find($post->listing_post_id);
        $listingUuid = $listing === null ? null : MlsListing::query()->where('integration', $listing->integration)->where('listing_key', $listing->listing_key)->value('uuid');

        return $this->fill($user, $post, $body, $media, $link, $picks, $when, $at, is_string($listingUuid) ? $listingUuid : null);
    }

    /**
     * The checks a post must pass before it's scheduled (screen 108).
     *
     * @param  list<string>  $media
     * @param  list<Pick>  $picks
     * @return array{attribution: string, checks: list<Check>, ok: bool, approval: bool, issues: list<Issue>}
     */
    public function check(User $user, string $body, array $media, array $picks, ?string $listing): array
    {
        $attribution = $this->attribution($user, $listing);
        $channels = array_values(array_unique(array_column($picks, 'channel')));
        $limits = $this->limits($user);
        $checks = [
            ['code' => 'broker', 'ok' => $this->broker($user) !== null, 'detail' => null],
            ['code' => 'targets', 'ok' => $picks !== [], 'detail' => null],
        ];

        foreach ($channels as $channel) {
            $limit = $limits[$channel] ?? null;
            if ($limit === null) {
                continue;
            }
            $checks[] = ['code' => 'length', 'ok' => mb_strlen($body."\n\n".$attribution) <= $limit['max_text'], 'detail' => $channel];
            if ($limit['media'] === 'required') {
                $checks[] = ['code' => 'media', 'ok' => $media !== [], 'detail' => $channel];
            } elseif ($limit['media'] === 'video') {
                // YouTube Shorts and TikTok take a short video (B11.3).
                $checks[] = ['code' => 'video', 'ok' => array_filter($media, self::isVideo(...)) !== [], 'detail' => $channel];
            }
        }

        // A sales agent may not be presented as a broker, owner or president (22 TAC §535.155(d)).
        $agent = Licence::query()->where('user_id', $user->id)->where('state_code', $this->states->current($user))->first();
        $title = $agent?->type === LicenceType::SalesAgent && preg_match("/\\b(i am|i'm|as your|your)\\s+(real estate\\s+)?(broker|owner|president)\\b/i", $body) === 1;
        $checks[] = ['code' => 'title', 'ok' => ! $title, 'detail' => null];

        // Fair housing wording, misleading claims and the brokerage's banned phrases (B12.1, screen 167).
        $issues = $this->policy->issues($user, $body);

        foreach (['fair_housing', 'claims', 'banned'] as $code) {
            $found = array_values(array_filter($issues, static fn (array $i): bool => $i['code'] === $code));
            $checks[] = ['code' => $code, 'ok' => $found === [], 'detail' => $found === [] ? null : implode(', ', array_column($found, 'phrase'))];
        }

        return [
            'attribution' => $attribution, 'checks' => $checks, 'ok' => array_filter($checks, static fn (array $c): bool => ! $c['ok']) === [],
            'approval' => $this->policy->needsApproval($user), 'issues' => $issues,
        ];
    }

    /**
     * "Fix automatically" (screen 167): flagged wording swapped or taken out.
     *
     * @return array{body: string, fixed: list<Issue>}
     */
    public function fix(User $user, string $body): array
    {
        return ['body' => $this->policy->fix($user, $body), 'fixed' => $this->policy->issues($user, $body)];
    }

    /** Remove a draft or a scheduled post (Undo restores it as a draft). */
    public function delete(User $user, string $uuid): void
    {
        $post = $this->find($user, $uuid);

        if (! in_array($post->status, ['draft', 'approval', 'scheduled'], true)) {
            throw ValidationException::withMessages(['post' => self::line('social.errors.sent')]);
        }

        $post->forceFill(['status' => 'draft', 'scheduled_at' => null])->save();
        $post->delete();
    }

    /** @return PostRow */
    public function restore(User $user, string $uuid): array
    {
        $post = SocialPost::onlyTrashed()->where('user_id', $user->id)->where('uuid', $uuid)->firstOrFail();
        $post->restore();

        return $this->row($post->refresh());
    }

    /**
     * Post it again: a new draft with the same text, media and places.
     *
     * @return PostRow
     */
    public function repost(User $user, string $uuid): array
    {
        $old = $this->find($user, $uuid);
        $post = SocialPost::query()->create([
            'uuid' => (string) Str::uuid7(), 'user_id' => $user->id, 'body' => $old->body, 'media' => $old->media, 'link' => $old->link,
            'attribution' => $old->attribution, 'listing_post_id' => $old->listing_post_id, 'status' => 'draft',
        ]);

        foreach ($old->targets as $target) {
            $post->targets()->create(['connection_id' => $target->connection_id, 'integration' => $target->integration, 'channel' => $target->channel, 'target_id' => $target->target_id, 'target_name' => $target->target_name, 'status' => 'queued']);
        }

        return $this->row($post->load('targets'));
    }

    /** Due scheduled posts go out (every minute). */
    public function publishDue(): int
    {
        $count = 0;

        SocialPost::query()->where('status', 'scheduled')->where('scheduled_at', '<=', now())->with('user')->orderBy('scheduled_at')->limit(200)->get()
            ->each(function (SocialPost $post) use (&$count): void {
                // Checked again as it goes out (B12.1): the brokerage may have banned a phrase since.
                $issues = $this->policy->issues($post->user, $post->body);

                if ($issues !== []) {
                    $note = __('studio.checks.recheck', ['phrases' => implode(', ', array_column($issues, 'phrase'))]);
                    $post->forceFill(['status' => 'draft', 'scheduled_at' => null, 'approval_note' => is_string($note) ? $note : null])->save();

                    return;
                }

                $this->publish($post);
                $count++;
            });

        return $count;
    }

    /** Send each place its job. */
    public function publish(SocialPost $post): void
    {
        $post->forceFill(['status' => 'publishing'])->save();

        foreach ($post->targets()->where('status', 'queued')->get() as $target) {
            PublishSocialTarget::dispatch($target->id);
        }
    }

    /** The post's overall status from its places: published, partly or failed. */
    public static function rollUp(SocialPost $post): void
    {
        $statuses = $post->targets()->pluck('status')->all();

        if (in_array('queued', $statuses, true) || in_array('publishing', $statuses, true)) {
            return;
        }

        $published = count(array_filter($statuses, static fn ($s): bool => $s === 'published'));
        $status = match (true) {
            $published === count($statuses) => 'published',
            $published > 0 => 'partly',
            default => 'failed',
        };
        $post->forceFill(['status' => $status, 'published_at' => $published > 0 ? ($post->published_at ?? now()) : null])->save();
    }

    /**
     * What a place receives: the text with the attribution, the link, and media links the channel can fetch.
     *
     * @return array{text: string, link: string|null, media: list<array{url: string, kind: string}>}
     */
    public static function content(SocialPost $post): array
    {
        $media = [];

        foreach ($post->media as $item) {
            $path = $item['path'] ?? null;
            $media[] = [
                'url' => is_string($path) ? URL::temporarySignedRoute('social.media', now()->addHours(self::MEDIA_HOURS), ['path' => $path]) : $item['url'],
                'kind' => $item['kind'],
            ];
        }

        return ['text' => trim($post->body)."\n\n".$post->attribution, 'link' => $post->link, 'media' => $media];
    }

    /**
     * An image or short video from the agent's device, kept for their posts.
     *
     * @return array{path: string, url: string, kind: string}
     */
    public function upload(User $user, UploadedFile $file): array
    {
        $kind = str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image';
        $path = $file->storeAs("social/{$user->id}", Str::uuid7().'.'.($file->extension() !== '' ? $file->extension() : ($kind === 'video' ? 'mp4' : 'jpg')), self::MEDIA_DISK);

        if (! is_string($path)) {
            throw ValidationException::withMessages(['file' => self::line('social.errors.upload')]);
        }

        return ['path' => $path, 'url' => URL::temporarySignedRoute('social.media', now()->addHours(self::MEDIA_HOURS), ['path' => $path]), 'kind' => $kind];
    }

    /**
     * @param  list<string>  $media
     * @param  list<Pick>  $picks
     * @return PostRow
     */
    private function fill(User $user, SocialPost $post, string $body, array $media, ?string $link, array $picks, string $when, ?string $at, ?string $listing): array
    {
        $listingPost = null;

        if ($listing !== null) {
            $draft = $this->listingPosts->draft($user, $listing);
            // MLS photos must be ones the MLS allows; designs the studio made (B12.1) are the agent's own files.
            if (array_diff(array_filter($media, static fn (string $m): bool => str_starts_with($m, 'http')), array_column($draft['photos'], 'url')) !== []) {
                throw ValidationException::withMessages(['media' => self::line('listings.post.errors.photo')]);
            }
            $mls = MlsListing::query()->where('uuid', $listing)->first();
            $listingPost = $mls === null ? null : ListingPost::query()->where('user_id', $user->id)->where('integration', $mls->integration)->where('listing_key', $mls->listing_key)->first();
        }

        $items = $this->media($user, $media, $listing !== null);
        $targets = $this->resolve($user, $picks);
        $check = $this->check($user, $body, $media, $picks, $listing);

        if ($when !== 'draft' && ! $check['ok']) {
            throw ValidationException::withMessages(['checks' => self::line('social.errors.checks')]);
        }

        $scheduled = $when === 'later' && $at !== null ? CarbonImmutable::parse($at)->utc() : null;

        if ($when === 'later' && ($scheduled === null || $scheduled->lt(now()->addMinutes(5)))) {
            throw ValidationException::withMessages(['at' => self::line('social.errors.at')]);
        }

        // The brokerage sees this agent's posts first: they wait for approval, then go now or at their time.
        $approval = $when !== 'draft' && $check['approval'];

        DB::transaction(function () use ($post, $body, $items, $link, $check, $listingPost, $when, $scheduled, $targets, $approval): void {
            $post->forceFill([
                'body' => $body, 'media' => $items, 'link' => $link, 'attribution' => $check['attribution'], 'listing_post_id' => $listingPost?->id,
                'status' => match (true) {
                    $approval => 'approval',
                    $when === 'now' => 'publishing',
                    $when === 'later' => 'scheduled',
                    default => 'draft',
                },
                'scheduled_at' => $scheduled,
                // A planned draft leaves the plan once it's scheduled or sent (B12.3).
                'planned_on' => $when === 'draft' ? $post->planned_on : null,
                'approval_requested_at' => $approval ? now() : null, 'approved_by' => null, 'approved_at' => null, 'approval_note' => $approval ? null : $post->approval_note,
            ])->save();
            $post->targets()->delete();

            foreach ($targets as $target) {
                $post->targets()->create([...$target, 'status' => 'queued']);
            }
        });

        if ($approval) {
            $brokerage = $this->policy->brokerage($user);

            foreach ($brokerage === null ? [] : $this->memberships->holdersIn(Role::Broker, $brokerage) as $broker) {
                $broker->notify(new PostApprovalRequested($post->setRelation('user', $user)));
            }
        } elseif ($when === 'now') {
            $this->publish($post);
        }

        return $this->row($post->refresh()->load('targets'));
    }

    /**
     * Media as stored: an uploaded path of the agent's, or a listing photo URL.
     *
     * @param  list<string>  $media
     * @return list<array{url: string, kind: string, path?: string}>
     */
    private function media(User $user, array $media, bool $fromListing): array
    {
        $items = [];

        foreach ($media as $value) {
            if (str_starts_with($value, "social/{$user->id}/") && Storage::disk(self::MEDIA_DISK)->exists($value)) {
                $kind = self::isVideo($value) ? 'video' : 'image';
                $items[] = ['url' => '', 'kind' => $kind, 'path' => $value];
            } elseif ($fromListing && str_starts_with($value, 'http')) {
                $items[] = ['url' => $value, 'kind' => 'image'];
            } else {
                throw ValidationException::withMessages(['media' => self::line('social.errors.media')]);
            }
        }

        return $items;
    }

    /**
     * The chosen places, checked against the agent's own connections.
     *
     * @param  list<Pick>  $picks
     * @return list<array{connection_id: int, integration: string, channel: string, target_id: string, target_name: string}>
     */
    private function resolve(User $user, array $picks): array
    {
        $resolved = [];

        foreach ($picks as $pick) {
            $connection = Connection::query()->where('owner_type', Connection::OWNER_USER)->where('owner_id', $user->id)->find($pick['connection']);
            $target = $connection === null ? null : collect($this->accounts->targets($connection))->first(static fn (array $t): bool => $t['channel'] === $pick['channel'] && $t['id'] === $pick['id']);

            if ($connection === null || $target === null) {
                throw ValidationException::withMessages(['targets' => self::line('social.errors.target')]);
            }

            $resolved[] = ['connection_id' => $connection->id, 'integration' => $connection->integration_id, 'channel' => $target['channel'], 'target_id' => $target['id'], 'target_name' => $target['name']];
        }

        return $resolved;
    }

    /** @return array<string, array{media: string, max_text: int}> */
    private function limits(User $user): array
    {
        $limits = [];

        foreach ($this->accounts->for($user) as $account) {
            $limits = [...$limits, ...$account['channels']];
        }

        return $limits;
    }

    /** "James Miller · Linda Park Realty", and the MLS's line for a listing post. */
    private function attribution(User $user, ?string $listing): string
    {
        $broker = $this->broker($user);
        $line = trim((string) $user->name).($broker !== null ? ' · '.$broker : '');

        if ($listing !== null) {
            $line .= "\n".$this->listingPosts->draft($user, $listing)['attribution'];
        }

        return $line;
    }

    /** The agent's brokerage, or the sponsoring broker on their licence. */
    private function broker(User $user): ?string
    {
        $membership = $this->roles->for($user);

        if ($membership !== null && $membership->role === Role::Agent && $membership->organisation->type === OrganisationType::Brokerage) {
            return $membership->organisation->name;
        }

        $sponsor = Licence::query()->where('user_id', $user->id)->where('state_code', $this->states->current($user))->first()?->sponsor_name;

        return is_string($sponsor) && $sponsor !== '' ? $sponsor : null;
    }

    private function find(User $user, string $uuid): SocialPost
    {
        return SocialPost::query()->where('user_id', $user->id)->where('uuid', $uuid)->with('targets')->firstOrFail();
    }

    /** @return PostRow */
    private function row(SocialPost $post): array
    {
        $targets = [];
        $totals = [];

        foreach ($post->targets as $t) {
            $insights = $t->insights ?? [];
            foreach ($insights as $key => $value) {
                $totals[$key] = ($totals[$key] ?? 0) + $value;
            }
            $targets[] = [
                'id' => $t->id, 'integration' => $t->integration, 'channel' => $t->channel, 'target_id' => $t->target_id, 'name' => $t->target_name, 'status' => $t->status,
                'error' => $t->last_error, 'url' => $t->url, 'published_at' => $t->published_at?->toIso8601String(), 'insights' => $insights,
            ];
        }

        $content = self::content($post);

        return [
            'id' => $post->uuid, 'body' => $post->body, 'media' => $content['media'], 'link' => $post->link, 'attribution' => $post->attribution,
            'listing' => $post->listing_post_id === null ? null : (string) $post->listing_post_id, 'status' => $post->status,
            'scheduled_at' => $post->scheduled_at?->toIso8601String(), 'published_at' => $post->published_at?->toIso8601String(), 'created_at' => $post->created_at?->toIso8601String(),
            'targets' => $targets, 'totals' => $totals, 'approval_note' => $post->approval_note,
        ];
    }

    private static function isVideo(string $media): bool
    {
        return preg_match('/\.(mp4|mov|m4v)$/i', $media) === 1;
    }

    private static function line(string $key): string
    {
        $line = __($key);

        return is_string($line) ? $line : $key;
    }
}
