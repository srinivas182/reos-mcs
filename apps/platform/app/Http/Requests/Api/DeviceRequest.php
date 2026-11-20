<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Identity\DeviceInfo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Shared rules for sign-in requests that carry the device signing in. */
abstract class DeviceRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    protected function deviceRules(): array
    {
        return [
            'device' => ['required', 'array:id,name,platform,app_version'],
            'device.id' => ['required', 'uuid'],
            'device.name' => ['required', 'string', 'max:100'],
            'device.platform' => ['required', 'in:ios,android,web'],
            'device.app_version' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function device(): DeviceInfo
    {
        $version = $this->input('device.app_version');

        return new DeviceInfo(
            id: $this->string('device.id')->lower()->toString(),
            name: $this->string('device.name')->squish()->toString(),
            platform: $this->string('device.platform')->toString(),
            appVersion: is_string($version) ? $version : null,
        );
    }
}
