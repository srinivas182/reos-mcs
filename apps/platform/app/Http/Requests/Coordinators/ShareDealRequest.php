<?php

declare(strict_types=1);

namespace App\Http\Requests\Coordinators;

use Illuminate\Foundation\Http\FormRequest;

/** Share a deal with a connected coordinator (B17.1), with access switches. */
final class ShareDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['coordinator_id' => ['required', 'integer'], 'switches' => ['present', 'array'], 'switches.dates' => ['sometimes', 'boolean'], 'switches.documents' => ['sometimes', 'boolean'], 'switches.parties' => ['sometimes', 'boolean'], 'switches.messages' => ['sometimes', 'boolean']];
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
