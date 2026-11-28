<?php

declare(strict_types=1);

namespace App\Http\Requests\Providers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/** A profile photo or a logo (B15.3): JPEG, PNG or WebP, up to 5 MB. */
final class ProfilePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['kind' => ['required', 'string', 'in:photo,logo'], 'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120']];
    }

    public function upload(): UploadedFile
    {
        $file = $this->file('image');

        return $file instanceof UploadedFile ? $file : abort(422);
    }
}
