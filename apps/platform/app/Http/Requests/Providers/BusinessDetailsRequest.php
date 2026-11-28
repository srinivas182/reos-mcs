<?php

declare(strict_types=1);

namespace App\Http\Requests\Providers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/** Business details and a photo ID, for a category without a licence (B15.2). */
final class BusinessDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'address' => ['required', 'string', 'min:5', 'max:240'],
            /** A photo of a driver's license or passport: JPEG, PNG or PDF, up to 10 MB. Needed the first time. */
            'id_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ];
    }

    public function idPhoto(): ?UploadedFile
    {
        $file = $this->file('id_photo');

        return $file instanceof UploadedFile ? $file : null;
    }
}
