<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Notification settings (B6.2): channels per kind, digests, quiet hours and time zone. */
final class NotificationSettingsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'channels' => ['sometimes', 'array'],
            'channels.*' => ['array'],
            'channels.*.*' => ['boolean'],
            'digests' => ['sometimes', 'array'],
            'digests.*' => ['nullable', Rule::in(['daily', 'weekly'])],
            'quiet' => ['sometimes', 'array'],
            'quiet.enabled' => ['required_with:quiet', 'boolean'],
            'quiet.start' => ['required_with:quiet', 'date_format:H:i'],
            'quiet.end' => ['required_with:quiet', 'date_format:H:i', 'different:quiet.start'],
            'timezone' => ['sometimes', 'timezone:all'],
        ];
    }

    /** @return array{channels?: array<string, array<string, bool>>, digests?: array<string, string|null>, quiet?: array{enabled: bool, start: string, end: string}, timezone?: string} */
    public function changes(): array
    {
        $changes = [];
        $channels = $this->input('channels');

        if (is_array($channels)) {
            foreach ($channels as $kind => $choices) {
                if (is_string($kind) && is_array($choices)) {
                    foreach ($choices as $channel => $on) {
                        if (is_string($channel)) {
                            $changes['channels'][$kind][$channel] = filter_var($on, FILTER_VALIDATE_BOOLEAN);
                        }
                    }
                }
            }
        }

        $digests = $this->input('digests');

        if (is_array($digests)) {
            foreach ($digests as $kind => $frequency) {
                if (is_string($kind)) {
                    $changes['digests'][$kind] = is_string($frequency) ? $frequency : null;
                }
            }
        }

        if ($this->has('quiet')) {
            $changes['quiet'] = [
                'enabled' => $this->boolean('quiet.enabled'),
                'start' => $this->string('quiet.start')->toString(),
                'end' => $this->string('quiet.end')->toString(),
            ];
        }

        if ($this->filled('timezone')) {
            $changes['timezone'] = $this->string('timezone')->toString();
        }

        return $changes;
    }
}
