<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Tenants;

use App\Tenancy\TenantStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\RequiredIf;

/** Suspend, archive or restore a tenant (screen 60). A reason is required to suspend or archive. */
final class TenantStatusRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|Enum|RequiredIf|string>> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TenantStatus::class)->except([TenantStatus::Trial])],
            'reason' => [Rule::requiredIf(fn (): bool => in_array($this->input('status'), [TenantStatus::Suspended->value, TenantStatus::Archived->value], true)), 'nullable', 'string', 'min:5', 'max:500'],
        ];
    }

    public function status(): TenantStatus
    {
        return TenantStatus::from($this->string('status')->toString());
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }
}
