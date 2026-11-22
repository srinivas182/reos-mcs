<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

/** A mobile number for text alerts (B6.2c), and the code sent to it when verifying. */
final class TextNumberRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            /**
             * US mobile number in international format.
             *
             * @example +15125550100
             */
            'phone' => ['required', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            /** The 6-digit code, when verifying. */
            'code' => [$this->routeIs('*.verify') ? 'required' : 'prohibited', 'string', 'regex:/^[0-9]{4,10}$/'],
        ];
    }

    public function phone(): string
    {
        return $this->string('phone')->toString();
    }

    public function code(): string
    {
        return $this->string('code')->toString();
    }
}
