<?php

declare(strict_types=1);

namespace App\Http\Requests\Sessions;

use Illuminate\Foundation\Http\FormRequest;

/** Agree or not to recording the session (B6.3c). */
final class RecordingAnswerRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['agreed' => ['required', 'boolean']];
    }
}
