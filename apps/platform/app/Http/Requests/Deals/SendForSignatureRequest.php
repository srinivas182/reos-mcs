<?php

declare(strict_types=1);

namespace App\Http\Requests\Deals;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Send for signature from a deal (B9.3): the subject, the deal's files in order, and the signers. */
final class SendForSignatureRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return [
            /** The email subject signers see. */
            'subject' => ['required', 'string', 'min:3', 'max:100'],
            /**
             * The deal's files to send, in order (uploads or signed copies).
             *
             * @var list<string>
             */
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['required', 'uuid', 'distinct'],
            /**
             * Who signs, and in what order (1 signs first; the same number signs at the same time).
             *
             * @var list<array{name: string, email: string, routing_order: int}>
             */
            'signers' => ['required', 'array', 'min:1', 'max:10'],
            'signers.*.name' => ['required', 'string', 'max:100'],
            'signers.*.email' => ['required', 'email:rfc', 'max:200'],
            'signers.*.routing_order' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }

    /** @return array{subject: string, files: list<string>, signers: list<array{name: string, email: string, routing_order: int}>} */
    public function values(): array
    {
        $signers = [];
        foreach ((array) $this->input('signers', []) as $s) {
            if (is_array($s) && is_string($s['name'] ?? null) && is_string($s['email'] ?? null) && is_numeric($s['routing_order'] ?? null)) {
                $signers[] = ['name' => trim($s['name']), 'email' => mb_strtolower(trim($s['email'])), 'routing_order' => (int) $s['routing_order']];
            }
        }

        return [
            'subject' => trim($this->string('subject')->toString()),
            'files' => array_values(array_filter((array) $this->input('files', []), is_string(...))),
            'signers' => $signers,
        ];
    }
}
