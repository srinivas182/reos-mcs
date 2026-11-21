<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Enums\OrganisationType;
use App\Models\Organisation;

/**
 * How a tenant looks (A4.2, screen 57): its name, logo, primary colour and
 * app label, and the sender name and reply-to on its emails. Stored as JSON
 * on the organisation; anything not set falls back to REOS.
 */
final readonly class Brand
{
    public function __construct(
        public int $organisationId,
        public string $slug,
        public OrganisationType $type,
        public string $name,
        public ?BrandColour $primary,
        /** "Texas REALTORS® Learning": what the app is called for its members. */
        public ?string $appLabel,
        public ?string $logoPath,
        public ?string $emailFromName,
        public ?string $replyTo,
    ) {}

    public static function of(Organisation $organisation): self
    {
        $brand = $organisation->brand ?? [];
        $text = static function (string $key) use ($brand): ?string {
            $value = $brand[$key] ?? null;

            return $value === null || $value === '' ? null : $value;
        };

        return new self(
            organisationId: $organisation->id,
            slug: $organisation->slug,
            type: $organisation->type,
            name: $organisation->name,
            primary: ($colour = $text('primary')) === null ? null : BrandColour::parse($colour),
            appLabel: $text('app_label'),
            logoPath: $text('logo_path'),
            emailFromName: $text('email_from_name'),
            replyTo: $text('reply_to'),
        );
    }

    /** Whether the tenant has set anything that changes how REOS looks. */
    public function isSet(): bool
    {
        return $this->primary !== null || $this->logoPath !== null || $this->appLabel !== null;
    }

    /** @return array<string, mixed> What the web app and the phone app need to show it. */
    public function toArray(): array
    {
        return [
            'organisation_id' => $this->organisationId,
            'type' => $this->type->value,
            'name' => $this->name,
            'app_label' => $this->appLabel,
            'logo_url' => $this->logoPath === null ? null : route('brand.logo', ['tenant' => $this->slug, 'v' => substr(md5($this->logoPath), 0, 8)]),
            'primary' => $this->primary?->hex,
            'palette' => $this->primary === null ? null : [
                'action' => $this->primary->hex,
                'pressed' => $this->primary->pressed()->hex,
                'tint' => $this->primary->tint()->hex,
            ],
        ];
    }
}
