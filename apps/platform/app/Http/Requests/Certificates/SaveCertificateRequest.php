<?php

declare(strict_types=1);

namespace App\Http\Requests\Certificates;

use Illuminate\Foundation\Http\FormRequest;

/** A read certificate, as the person checked it (B10.3b). */
final class SaveCertificateRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'upload' => ['required', 'uuid'],
            'state' => ['required', 'string', 'size:2'],
            'title' => ['required', 'string', 'max:200'],
            'hours' => ['required', 'numeric', 'min:0.5', 'max:60'],
            'completed_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'provider' => ['nullable', 'string', 'max:200'],
        ];
    }

    /** @return array{state: string, title: string, hours: float, completed_on: string, provider: string|null} */
    public function values(): array
    {
        return [
            'state' => $this->string('state')->toString(),
            'title' => $this->string('title')->trim()->toString(),
            'hours' => round($this->float('hours'), 1),
            'completed_on' => $this->string('completed_on')->toString(),
            'provider' => $this->filled('provider') ? $this->string('provider')->trim()->toString() : null,
        ];
    }
}
