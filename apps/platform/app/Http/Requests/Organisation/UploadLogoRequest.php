<?php

declare(strict_types=1);

namespace App\Http\Requests\Organisation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/** A new logo (screen 57): PNG, JPEG or WebP up to 2 MB, at least 64 pixels. Re-drawn on save. */
final class UploadLogoRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|File|string>> */
    public function rules(): array
    {
        return [
            'logo' => ['required', File::image()->types(['png', 'jpg', 'jpeg', 'webp'])->max(2048)->dimensions(Rule::dimensions()->minWidth(64)->minHeight(64)->maxWidth(4000)->maxHeight(4000))],
        ];
    }
}
