<?php

declare(strict_types=1);

namespace App\Http\Requests\Social;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** A photo (up to 8 MB) or a short video (up to 200 MB) for a post (B11). */
final class MediaRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/quicktime', 'max:204800']];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $file = $this->file('file');
            if ($file !== null && ! is_array($file) && str_starts_with((string) $file->getMimeType(), 'image/') && $file->getSize() > 8 * 1024 * 1024) {
                $line = __('social.errors.imageSize');
                $validator->errors()->add('file', is_string($line) ? $line : 'social.errors.imageSize');
            }
        }];
    }
}
