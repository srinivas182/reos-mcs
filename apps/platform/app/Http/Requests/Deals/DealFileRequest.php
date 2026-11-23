<?php

declare(strict_types=1);

namespace App\Http\Requests\Deals;

use App\Deals\DealFiles;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/** A PDF added to a deal to send for signature (B9.3), up to 25 MB. */
final class DealFileRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return [
            /** A PDF, up to 25 MB. */
            'file' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:'.DealFiles::MAX_KB],
        ];
    }

    public function upload(): UploadedFile
    {
        $file = $this->file('file');

        return $file instanceof UploadedFile ? $file : abort(422);
    }
}
