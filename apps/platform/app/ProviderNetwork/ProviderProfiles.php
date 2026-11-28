<?php

declare(strict_types=1);

namespace App\ProviderNetwork;

use App\Compliance\ComplianceEngine;
use App\Models\ProviderLicence;
use App\Models\ProviderProfile;
use App\Models\ProviderServiceArea;
use App\Models\User;
use App\Services\Ai\Bedrock;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * The provider profile (B15.3, ADR 0075, provider screen 7) and the agent's
 * view of it.
 *
 * - **Builder:** photo and logo, a short bio in English and Spanish ("Write
 *   it for me" drafts both), services, specialities, a response-time goal,
 *   free resources and expert sessions.
 * - **No contact details:** phone numbers, emails and web addresses are
 *   refused in the text; agents reach providers through REOS.
 * - **Checked before it's published:** every save goes through the
 *   compliance engine (the `provider` channel: misleading claims and fair
 *   housing). Agents see the last version that passed.
 * - **The agent's view:** live profiles only, with the licence badge and
 *   when it was checked, the areas and the services.
 *
 * @phpstan-import-type Issue from ComplianceEngine
 *
 * @phpstan-type FreeResource array{title: string, url: string}
 * @phpstan-type Session array{title: string, minutes: int}
 * @phpstan-type Card array{id: int, name: string, category: string|null, photo_url: string|null, logo_url: string|null, bio: string|null, services: list<string>, specialities: list<string>, response_hours: int|null, resources: list<FreeResource>, sessions: list<Session>, licence: array{kind: string, number: string|null, verified: bool, checked_at: string|null, via: string|null}, areas: list<string>, tier: string|null, live: bool}
 * @phpstan-type Builder array{draft: array{bio: string|null, bio_es: string|null, services: list<string>, specialities: list<string>, response_hours: int|null, resources: list<FreeResource>, sessions: list<Session>}, issues: list<Issue>, published_at: string|null, preview: Card|null, response_choices: list<int>}
 */
final readonly class ProviderProfiles
{
    /** Response-time goals a provider can promise, in hours. */
    public const RESPONSE_HOURS = [1, 2, 4, 8, 24];

    private const CONTACT = [
        '/[\w.+-]+@[\w-]+\.[\w.-]+/u',
        '/(?:\+?1[\s.-]?)?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}\b/',
        '/https?:\/\/|www\.|\b[\w-]+\.(?:com|net|org|io|co|us)\b/i',
    ];

    public function __construct(private ProviderOnboarding $onboarding, private ComplianceEngine $compliance, private Bedrock $bedrock, private AuditLogger $audit) {}

    /** @return Builder */
    public function builder(User $user): array
    {
        $profile = $this->onboarding->profile($user)->load(['category', 'areas', 'user']);

        return [
            'draft' => [
                'bio' => $profile->bio, 'bio_es' => $profile->bio_es, 'services' => $profile->services ?? [], 'specialities' => $profile->specialities ?? [],
                'response_hours' => $profile->response_hours, 'resources' => $profile->resources ?? [], 'sessions' => $profile->sessions ?? [],
            ],
            'issues' => self::issues($profile),
            'published_at' => $profile->published_at?->toIso8601String(),
            'preview' => $profile->category === null ? null : $this->card($profile, app()->getLocale(), true),
            'response_choices' => self::RESPONSE_HOURS,
        ];
    }

    /**
     * Saves the profile. The text is checked first: contact details are
     * refused; wording the compliance engine blocks is kept as a draft, with
     * the issues, and agents go on seeing the last version that passed.
     *
     * @param  array{bio: string|null, bio_es: string|null, services: list<string>, specialities: list<string>, response_hours: int|null, resources: list<FreeResource>, sessions: list<Session>}  $data
     * @return Builder
     */
    public function save(User $user, array $data): array
    {
        $profile = $this->onboarding->profile($user)->load('category');

        if ($profile->category === null) {
            throw ValidationException::withMessages(['category' => __('providers.join.errors.chooseFirst')]);
        }

        $this->refuseContacts($data);

        foreach ($data['resources'] as $i => $resource) {
            if (! str_starts_with($resource['url'], 'https://')) {
                throw ValidationException::withMessages(["resources.{$i}.url" => __('providers.profile.errors.https')]);
            }
        }

        $text = trim(implode("\n", array_filter([$data['bio'], $data['bio_es'], implode(', ', $data['services']), implode(', ', $data['specialities']), ...array_column($data['resources'], 'title'), ...array_column($data['sessions'], 'title')])));
        $result = $text === '' ? ['ok' => true, 'issues' => []] : $this->compliance->check($user, ['channel' => 'provider', 'text' => $text, 'broker' => null]);

        $profile->forceFill([...$data, 'profile_issues' => $result['issues']]);

        if ($result['ok']) {
            $profile->forceFill(['published' => $data, 'published_at' => now()]);
        }

        $profile->save();
        $this->audit->record($result['ok'] ? 'provider.profile_published' : 'provider.profile_blocked', 'user', (string) $user->id, ['issues' => array_column($result['issues'], 'code')], $user, $profile->organisation_id);

        return $this->builder($user);
    }

    /**
     * "Write it for me": a bio in English and Spanish from the provider's details.
     *
     * @return array{bio: string, bio_es: string, engine: 'ai'|'simulator'}
     */
    public function draftBio(User $user): array
    {
        $profile = $this->onboarding->profile($user)->load(['category', 'areas']);

        if ($profile->category === null) {
            throw ValidationException::withMessages(['category' => __('providers.join.errors.chooseFirst')]);
        }

        $areas = $profile->areas->map(self::areaLabel(...))->all();
        $facts = [
            'name' => $profile->business_name ?? $user->name, 'category' => $profile->category->name, 'details' => $profile->fields ?? [],
            'services' => $profile->services ?? [], 'specialities' => $profile->specialities ?? [], 'areas' => $areas, 'response_hours' => $profile->response_hours,
        ];
        $bedrock = $this->bedrock->client(30);

        if ($bedrock !== null) {
            try {
                return [...(new ClaudeProviderBio($bedrock['client'], $bedrock['model']))->write($facts), 'engine' => 'ai'];
            } catch (Throwable $e) {
                Log::warning('provider.bio_failed', ['error' => $e->getMessage()]);
            }
        }

        // Without Claude: a plain bio from the same facts.
        $line = static function (string $key, array $replace, string $locale): string {
            $text = __($key, $replace, $locale);

            return is_string($text) ? $text : '';
        };
        $place = $areas === [] ? null : implode(', ', array_slice($areas, 0, 3));
        $services = array_slice($profile->services ?? [], 0, 3);
        $bio = static fn (string $locale): string => trim(implode(' ', array_filter([
            $line('providers.profile.draft.intro', ['name' => (string) $facts['name'], 'category' => mb_strtolower($profile->category->name)], $locale),
            $place === null ? null : $line('providers.profile.draft.areas', ['areas' => $place], $locale),
            $services === [] ? null : $line('providers.profile.draft.services', ['services' => implode(', ', $services)], $locale),
            $profile->response_hours === null ? null : $line('providers.profile.draft.response', ['hours' => (string) $profile->response_hours], $locale),
        ])));

        return ['bio' => $bio('en'), 'bio_es' => $bio('es'), 'engine' => 'simulator'];
    }

    /** A photo or logo (B15.3), on the content disk. */
    public function photo(User $user, string $kind, UploadedFile $file): void
    {
        $profile = $this->onboarding->profile($user);
        $disk = config()->string('reos.content.disk');
        $column = $kind === 'logo' ? 'logo_path' : 'photo_path';
        $old = $profile->getAttribute($column);
        $path = $file->storeAs("provider-photos/{$user->id}", Str::uuid7().'.'.($file->guessExtension() ?? 'jpg'), $disk);
        $profile->forceFill([$column => $path ?: null])->save();

        if (is_string($old)) {
            Storage::disk($disk)->delete($old);
        }
    }

    /**
     * Live, verified providers with a published profile, for agents.
     *
     * @return list<Card>
     */
    public function directory(?string $category, ?string $place, string $locale): array
    {
        $place = $place === null ? null : trim($place);
        $cards = [];
        $profiles = ProviderProfile::query()->with(['category', 'areas', 'user'])->where('status', ProviderProfile::LIVE)->whereNotNull('published')
            ->when($category !== null, static fn ($q) => $q->whereHas('category', static fn ($c) => $c->where('key', $category)))
            ->when($place !== null && $place !== '', static fn ($q) => $q->whereHas('areas', static fn ($a) => $a->whereRaw('lower(city) = ?', [mb_strtolower((string) $place)])->orWhere('postal_code', $place)))
            ->orderByRaw("CASE tier WHEN 'premium' THEN 0 WHEN 'pro' THEN 1 ELSE 2 END")->orderBy('live_at')->limit(100)->get();

        foreach ($profiles as $profile) {
            $cards[] = $this->card($profile, $locale);
        }

        return $cards;
    }

    /** @return Card */
    public function show(User $viewer, int $id, string $locale): array
    {
        $profile = ProviderProfile::query()->with(['category', 'areas', 'user'])->find($id);
        $own = $profile !== null && $profile->user_id === $viewer->id;

        if ($profile === null || (! $own && ($profile->status !== ProviderProfile::LIVE || $profile->published === null))) {
            throw new NotFoundHttpException;
        }

        return $this->card($profile, $locale, $own);
    }

    /**
     * The profile as an agent sees it: the published version, or the draft for the provider's own preview.
     *
     * @return Card
     */
    public function card(ProviderProfile $profile, string $locale, bool $draft = false): array
    {
        $content = $draft ? [
            'bio' => $profile->bio, 'bio_es' => $profile->bio_es, 'services' => $profile->services ?? [], 'specialities' => $profile->specialities ?? [],
            'response_hours' => $profile->response_hours, 'resources' => $profile->resources ?? [], 'sessions' => $profile->sessions ?? [],
        ] : ($profile->published ?? []);
        $kind = ProviderOnboarding::licenceKind($profile->category);
        $licence = ProviderLicence::query()->where('user_id', $profile->user_id)->where('kind', $kind === 'business' ? 'business_id' : $kind)->first();
        $text = static fn (mixed $v): ?string => is_string($v) && trim($v) !== '' ? $v : null;
        $list = static fn (mixed $v): array => is_array($v) ? array_values(array_filter($v, 'is_string')) : [];
        $bio = $locale === 'es' ? ($text($content['bio_es'] ?? null) ?? $text($content['bio'] ?? null)) : $text($content['bio'] ?? null);

        return [
            'id' => $profile->id, 'name' => (string) ($profile->business_name ?? $profile->user->name), 'category' => $profile->category?->name,
            'photo_url' => self::photoUrl($profile, 'photo'), 'logo_url' => self::photoUrl($profile, 'logo'),
            'bio' => $bio, 'services' => $list($content['services'] ?? null), 'specialities' => $list($content['specialities'] ?? null),
            'response_hours' => is_int($content['response_hours'] ?? null) ? $content['response_hours'] : null,
            'resources' => self::resources($content['resources'] ?? null), 'sessions' => self::sessions($content['sessions'] ?? null),
            'licence' => [
                'kind' => $kind, 'number' => $kind === 'business' ? null : $licence?->number,
                'verified' => $licence?->status === ProviderLicence::VERIFIED,
                'checked_at' => ($licence === null ? null : ($licence->reviewed_at ?? $licence->updated_at)?->toIso8601String()), 'via' => $licence?->checked_via,
            ],
            'areas' => array_values($profile->areas->map(self::areaLabel(...))->all()), 'tier' => $profile->tier, 'live' => $profile->status === ProviderProfile::LIVE,
        ];
    }

    public static function areaLabel(ProviderServiceArea $area): string
    {
        $label = match ($area->kind) {
            'city' => (string) $area->city,
            'zip' => (string) $area->postal_code,
            default => __('providers.onboarding.areas.radiusLabel', ['miles' => (string) $area->radius_miles, 'zip' => (string) $area->postal_code]),
        };

        return is_string($label) ? $label : '';
    }

    /** A link to the photo or logo that works for a day (the phone shows it without a session). */
    private static function photoUrl(ProviderProfile $profile, string $kind): ?string
    {
        $path = $kind === 'logo' ? $profile->logo_path : $profile->photo_path;

        return $path === null ? null : URL::temporarySignedRoute('providers.photo', now()->addDay(), ['profile' => $profile->id, 'kind' => $kind]);
    }

    /** @return list<Issue> */
    private static function issues(ProviderProfile $profile): array
    {
        $issues = [];

        foreach ($profile->profile_issues ?? [] as $issue) {
            $issues[] = [
                'code' => is_string($issue['code'] ?? null) ? $issue['code'] : '', 'severity' => ($issue['severity'] ?? null) === 'warn' ? 'warn' : 'block',
                'phrase' => is_string($issue['phrase'] ?? null) ? $issue['phrase'] : null, 'fix' => is_string($issue['fix'] ?? null) ? $issue['fix'] : null,
                'reason' => is_string($issue['reason'] ?? null) ? $issue['reason'] : null, 'citation' => is_string($issue['citation'] ?? null) ? $issue['citation'] : '',
                'url' => is_string($issue['url'] ?? null) ? $issue['url'] : '', 'source' => ($issue['source'] ?? null) === 'claude' ? 'claude' : 'rule',
            ];
        }

        return $issues;
    }

    /** @param  array{bio: string|null, bio_es: string|null, services: list<string>, specialities: list<string>, response_hours: int|null, resources: list<FreeResource>, sessions: list<Session>}  $data */
    private function refuseContacts(array $data): void
    {
        $fields = ['bio' => $data['bio'], 'bio_es' => $data['bio_es'], 'services' => implode(' ', $data['services']), 'specialities' => implode(' ', $data['specialities'])];

        foreach ($data['resources'] as $i => $r) {
            $fields["resources.{$i}.title"] = $r['title'];
        }

        foreach ($data['sessions'] as $i => $s) {
            $fields["sessions.{$i}.title"] = $s['title'];
        }

        foreach ($fields as $field => $value) {
            foreach (self::CONTACT as $pattern) {
                if (is_string($value) && preg_match($pattern, $value) === 1) {
                    throw ValidationException::withMessages([$field => __('providers.profile.errors.contact')]);
                }
            }
        }
    }

    /** @return list<FreeResource> */
    private static function resources(mixed $rows): array
    {
        $out = [];

        foreach (is_array($rows) ? $rows : [] as $r) {
            if (is_array($r) && is_string($r['title'] ?? null) && is_string($r['url'] ?? null)) {
                $out[] = ['title' => $r['title'], 'url' => $r['url']];
            }
        }

        return $out;
    }

    /** @return list<Session> */
    private static function sessions(mixed $rows): array
    {
        $out = [];

        foreach (is_array($rows) ? $rows : [] as $s) {
            if (is_array($s) && is_string($s['title'] ?? null) && is_int($s['minutes'] ?? null)) {
                $out[] = ['title' => $s['title'], 'minutes' => $s['minutes']];
            }
        }

        return $out;
    }
}
