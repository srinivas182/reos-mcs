<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Notifications\Centre\Push\PushDevices;
use Illuminate\Foundation\Http\FormRequest;

/** A phone turning on push notifications (B6.2b). */
final class PushDeviceRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255', 'regex:'.PushDevices::TOKEN],
            'platform' => ['required', 'in:ios,android'],
            'name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
