<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Where new contacts go (screen 24): one of your CRMs, or none (they stay in REOS). */
final class DefaultCrmRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return ['connection' => ['nullable', 'integer']];
    }

    public function connection(): ?int
    {
        return $this->filled('connection') ? $this->integer('connection') : null;
    }
}
