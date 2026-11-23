<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use App\Models\ContactImport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\In;

/** CSV import (B8.3b): the file, or how its columns map. */
final class ContactImportRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|File|In|string>> */
    public function rules(): array
    {
        return $this->isMethod('post') ? [
            'file' => ['required', File::types(['csv', 'txt'])->max(5 * 1024)],
        ] : [
            'mapping' => ['required', 'array', 'max:60'],
            'mapping.*' => ['string', new In([...ContactImport::FIELDS, 'ignore'])],
        ];
    }

    /** @return array<string, string> */
    public function mapping(): array
    {
        $mapping = [];
        foreach ((array) $this->input('mapping', []) as $header => $field) {
            if (is_string($field)) {
                $mapping[(string) $header] = $field;
            }
        }

        return $mapping;
    }
}
