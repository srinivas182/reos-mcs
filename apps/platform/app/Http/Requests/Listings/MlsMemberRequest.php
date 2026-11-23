<?php

declare(strict_types=1);

namespace App\Http\Requests\Listings;

use Illuminate\Foundation\Http\FormRequest;

/** An MLS member record, by its MLS (register id) and member key (B10.1). */
final class MlsMemberRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'mls' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            'member_key' => ['required', 'string', 'max:128'],
        ];
    }

    public function mls(): string
    {
        return $this->string('mls')->toString();
    }

    public function memberKey(): string
    {
        return $this->string('member_key')->toString();
    }
}
