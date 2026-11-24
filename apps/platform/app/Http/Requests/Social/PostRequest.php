<?php

declare(strict_types=1);

namespace App\Http\Requests\Social;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A social post: text, media, a link, the places it goes, and now, later or a draft (B11). */
final class PostRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'media' => ['present', 'array', 'max:10'],
            'media.*' => ['string', 'max:1000'],
            'link' => ['nullable', 'url:https', 'max:500'],
            'targets' => ['present', 'array', 'max:12'],
            'targets.*.connection' => ['required', 'integer'],
            'targets.*.channel' => ['required', 'string', 'max:20'],
            'targets.*.id' => ['required', 'string', 'max:120'],
            'when' => ['required', Rule::in(['now', 'later', 'draft'])],
            'at' => ['nullable', 'required_if:when,later', 'date'],
            // The listing (B10.2) a listing post comes from.
            'listing' => ['nullable', 'uuid'],
        ];
    }

    /** @return list<string> */
    public function media(): array
    {
        $media = $this->input('media');

        return is_array($media) ? array_values(array_filter($media, 'is_string')) : [];
    }

    /** @return list<array{connection: int, channel: string, id: string}> */
    public function targets(): array
    {
        $list = [];
        $targets = $this->input('targets');

        foreach (is_array($targets) ? $targets : [] as $t) {
            if (is_array($t) && is_numeric($t['connection'] ?? null) && is_string($t['channel'] ?? null) && is_string($t['id'] ?? null)) {
                $list[] = ['connection' => (int) $t['connection'], 'channel' => $t['channel'], 'id' => $t['id']];
            }
        }

        return $list;
    }

    public function optional(string $key): ?string
    {
        $value = $this->input($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
