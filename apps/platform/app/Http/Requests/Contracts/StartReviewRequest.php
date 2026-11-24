<?php

declare(strict_types=1);

namespace App\Http\Requests\Contracts;

use Illuminate\Foundation\Http\FormRequest;

/** Start a contract review (A12.2; screen 63): a PDF or photo, a deal's document, or the sample. */
final class StartReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'file' => ['nullable', 'required_without_all:deal_file,sample', 'file', 'extensions:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:20480'],
            'deal_file' => ['nullable', 'uuid'],
            'deal_id' => ['nullable', 'integer', 'min:1'],
            'sample' => ['nullable', 'boolean'],
        ];
    }
}
