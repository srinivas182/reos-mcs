<?php

declare(strict_types=1);

namespace App\Http\Requests\Coordinators;

use Illuminate\Foundation\Http\FormRequest;

/** Change what a coordinator can see on a shared deal (B17.1). */
final class ShareSwitchesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['switches' => ['present', 'array'], 'switches.dates' => ['sometimes', 'boolean'], 'switches.documents' => ['sometimes', 'boolean'], 'switches.parties' => ['sometimes', 'boolean'], 'switches.messages' => ['sometimes', 'boolean']];
    }

    /** @return array<string, bool> */
    public function switches(): array
    {
        $out = [];

        foreach (['dates', 'documents', 'parties', 'messages'] as $key) {
            if ($this->has('switches.'.$key)) {
                $out[$key] = $this->boolean('switches.'.$key);
            }
        }

        return $out;
    }
}
