<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

/**
 * Avatar consent (A7.1b): agreeing to the template, with the 2-minute sample
 * the avatar is made from (MP4, MOV or WebM, up to 500 MB).
 */
final class AvatarConsentRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|File|string>> */
    public function rules(): array
    {
        return [
            'agree' => ['accepted'],
            'sample' => ['required', File::types(['mp4', 'mov', 'webm'])->max(500 * 1024)],
        ];
    }
}
