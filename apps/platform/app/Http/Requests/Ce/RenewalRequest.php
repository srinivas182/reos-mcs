<?php

declare(strict_types=1);

namespace App\Http\Requests\Ce;

use Illuminate\Foundation\Http\FormRequest;

/** "I've renewed with TREC" on the renewal checklist, or Undo (A11.2). */
final class RenewalRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['renewed' => ['required', 'boolean']];
    }
}
