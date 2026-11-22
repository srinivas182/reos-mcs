<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Content;

use App\Content\MediaKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/** Asks for a signed upload link for one lesson file (A5.3): its kind, name and size. */
final class StartUploadRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|In|string>> */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(MediaKind::values())],
            'name' => ['required', 'string', 'max:190', 'not_regex:/[\\\\\/\x00]/'],
            'size' => ['required', 'integer', 'min:1'],
        ];
    }

    public function kind(): MediaKind
    {
        return MediaKind::from($this->string('kind')->toString());
    }
}
