<?php

declare(strict_types=1);

namespace App\Http\Requests\Organisation;

use App\Authorization\Role;
use App\Services\Tenancy\Invitations;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Invite one person, or many from pasted lines or a CSV (A4.3a, screens 32 and 41). */
final class SendInvitesRequest extends FormRequest
{
    /** @var array{people: list<array{email: string|null, phone: string|null, name: string|null}>, invalid: list<string>}|null */
    private ?array $parsed = null;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'mode' => ['required', 'in:one,many'],
            'contact' => ['nullable', 'required_if:mode,one', 'string', 'max:200'],
            'name' => ['nullable', 'string', 'max:120'],
            'people' => ['nullable', 'string', 'max:100000'],
            'file' => ['nullable', 'file', 'max:1024', 'mimes:csv,txt'],
            'role' => ['required', Rule::enum(Role::class)],
            'team_id' => ['nullable', 'integer'],
            'locale' => ['required', 'in:en,es'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $parsed = $this->parsed();

            if ($this->input('mode') === 'one' && $parsed['people'] === []) {
                $validator->errors()->add('contact', __('invites.errors.contact'));
            } elseif ($parsed['people'] === []) {
                $validator->errors()->add('people', __('invites.errors.nobody'));
            }
        }];
    }

    /** @return array{people: list<array{email: string|null, phone: string|null, name: string|null}>, invalid: list<string>} */
    public function parsed(): array
    {
        if ($this->parsed !== null) {
            return $this->parsed;
        }

        $invitations = app(Invitations::class);

        if ($this->input('mode') === 'one') {
            $contact = trim($this->string('contact')->toString());
            $email = filter_var($contact, FILTER_VALIDATE_EMAIL) !== false ? mb_strtolower($contact) : null;
            $phone = $email === null ? PhoneNumber::normalise($contact) : null;
            $name = $this->filled('name') ? trim($this->string('name')->toString()) : null;

            return $this->parsed = $email === null && $phone === null
                ? ['people' => [], 'invalid' => [$contact]]
                : ['people' => [['email' => $email, 'phone' => $phone, 'name' => $name]], 'invalid' => []];
        }

        $file = $this->file('file');
        $text = $this->string('people')->toString()."\n".($file instanceof UploadedFile ? (string) $file->get() : '');

        return $this->parsed = $invitations->parse($text);
    }

    public function role(): Role
    {
        return Role::from($this->string('role')->toString());
    }
}
