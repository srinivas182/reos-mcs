<?php

declare(strict_types=1);

namespace App\Http\Requests\Organisation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/** Single sign-on settings from screen 57 (A4.2c). */
final class SaveSsoRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'protocol' => ['required', 'in:saml,oidc'],
            'metadata_url' => ['nullable', 'string', 'url:http,https', 'max:2000'],
            'metadata_file' => ['nullable', 'file', 'max:1024', 'mimetypes:text/xml,application/xml,application/samlmetadata+xml,text/plain'],
            'issuer' => ['nullable', 'required_if:protocol,oidc', 'string', 'url:http,https', 'max:2000'],
            'client_id' => ['nullable', 'required_if:protocol,oidc', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array{protocol: string, metadata_url: string|null, metadata_xml: string|null, issuer: string|null, client_id: string|null, client_secret: string|null}
     */
    public function settings(): array
    {
        $file = $this->file('metadata_file');

        return [
            'protocol' => $this->string('protocol')->toString(),
            'metadata_url' => $this->filled('metadata_url') ? $this->string('metadata_url')->toString() : null,
            'metadata_xml' => $file instanceof UploadedFile ? (string) $file->get() : null,
            'issuer' => $this->filled('issuer') ? $this->string('issuer')->toString() : null,
            'client_id' => $this->filled('client_id') ? $this->string('client_id')->toString() : null,
            'client_secret' => $this->filled('client_secret') ? $this->string('client_secret')->toString() : null,
        ];
    }
}
