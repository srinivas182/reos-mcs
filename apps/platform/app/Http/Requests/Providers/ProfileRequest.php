<?php

declare(strict_types=1);

namespace App\Http\Requests\Providers;

use App\ProviderNetwork\ProviderProfiles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/** The provider profile (B15.3, provider screen 7). Phone numbers, emails and web addresses are refused in the text. */
final class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|In>> */
    public function rules(): array
    {
        return [
            'bio' => ['nullable', 'string', 'max:1200'],
            /** The bio in Spanish. */
            'bio_es' => ['nullable', 'string', 'max:1200'],
            'services' => ['present', 'array', 'max:20'],
            'services.*' => ['string', 'min:2', 'max:60'],
            'specialities' => ['present', 'array', 'max:20'],
            'specialities.*' => ['string', 'min:2', 'max:60'],
            /**
             * The response-time goal, in hours: 1, 2, 4, 8 or 24.
             *
             * @var int|null
             */
            'response_hours' => ['nullable', 'integer', Rule::in(ProviderProfiles::RESPONSE_HOURS)],
            /** Free resources for agents: a title and an https link each. */
            'resources' => ['present', 'array', 'max:10'],
            'resources.*.title' => ['required', 'string', 'min:2', 'max:80'],
            'resources.*.url' => ['required', 'string', 'url', 'max:300'],
            /** Expert sessions offered to agents: a title and a length in minutes. */
            'sessions' => ['present', 'array', 'max:10'],
            'sessions.*.title' => ['required', 'string', 'min:2', 'max:80'],
            'sessions.*.minutes' => ['required', 'integer', 'min:10', 'max:120'],
        ];
    }

    /** @return array{bio: string|null, bio_es: string|null, services: list<string>, specialities: list<string>, response_hours: int|null, resources: list<array{title: string, url: string}>, sessions: list<array{title: string, minutes: int}>} */
    public function profile(): array
    {
        $text = fn (string $k): ?string => is_string($this->validated($k)) && trim((string) $this->validated($k)) !== '' ? trim((string) $this->validated($k)) : null;
        $list = function (string $k): array {
            $v = $this->validated($k);

            return is_array($v) ? array_values(array_unique(array_map(static fn (mixed $s): string => trim(is_string($s) ? $s : ''), $v))) : [];
        };
        $resources = [];

        foreach ((array) $this->validated('resources') as $r) {
            if (is_array($r) && is_string($r['title'] ?? null) && is_string($r['url'] ?? null)) {
                $resources[] = ['title' => trim($r['title']), 'url' => trim($r['url'])];
            }
        }

        $sessions = [];

        foreach ((array) $this->validated('sessions') as $s) {
            if (is_array($s) && is_string($s['title'] ?? null) && is_numeric($s['minutes'] ?? null)) {
                $sessions[] = ['title' => trim($s['title']), 'minutes' => (int) $s['minutes']];
            }
        }

        $hours = $this->validated('response_hours');

        return [
            'bio' => $text('bio'), 'bio_es' => $text('bio_es'), 'services' => $list('services'), 'specialities' => $list('specialities'),
            'response_hours' => is_numeric($hours) ? (int) $hours : null, 'resources' => $resources, 'sessions' => $sessions,
        ];
    }
}
