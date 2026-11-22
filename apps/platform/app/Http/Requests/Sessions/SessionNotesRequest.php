<?php

declare(strict_types=1);

namespace App\Http\Requests\Sessions;

use Illuminate\Foundation\Http\FormRequest;

/** Session notes (screen 16). */
final class SessionNotesRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['notes' => ['present', 'nullable', 'string', 'max:20000']];
    }

    public function notes(): ?string
    {
        $notes = $this->input('notes');

        return is_string($notes) && trim($notes) !== '' ? $notes : null;
    }
}
