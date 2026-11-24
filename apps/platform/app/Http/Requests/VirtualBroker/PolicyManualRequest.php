<?php

declare(strict_types=1);

namespace App\Http\Requests\VirtualBroker;

use Illuminate\Foundation\Http\FormRequest;

/** A new version of the policy manual (A12.1; broker web screen 34): PDF, Word or text, up to 20 MB. */
final class PolicyManualRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:pdf,docx,txt,md', 'mimetypes:application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/zip,text/plain,text/markdown', 'max:20480'],
        ];
    }
}
