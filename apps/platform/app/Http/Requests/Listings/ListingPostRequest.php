<?php

declare(strict_types=1);

namespace App\Http\Requests\Listings;

use Illuminate\Foundation\Http\FormRequest;

/** A listing post: the caption and the chosen MLS photos, in order (B10.2). */
final class ListingPostRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'caption' => ['required', 'string', 'max:1800'],
            'photos' => ['present', 'array', 'max:50'],
            'photos.*' => ['string', 'max:2048'],
        ];
    }

    public function caption(): string
    {
        return $this->string('caption')->trim()->toString();
    }

    /** @return list<string> */
    public function photos(): array
    {
        $photos = $this->input('photos');

        return is_array($photos) ? array_values(array_filter($photos, is_string(...))) : [];
    }
}
