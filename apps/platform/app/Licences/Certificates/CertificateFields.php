<?php

declare(strict_types=1);

namespace App\Licences\Certificates;

/** What a certificate says, as read, each with the reader's confidence (0–100). */
final readonly class CertificateFields
{
    /**
     * @param  array<string, float>  $confidence  field => 0–100
     */
    public function __construct(
        public ?string $name,
        public ?string $course,
        public ?float $hours,
        public ?string $completedOn,
        public ?string $provider,
        public array $confidence = [],
    ) {}

    /** @return array{name: string|null, course: string|null, hours: float|null, completed_on: string|null, provider: string|null, confidence: array<string, float>} */
    public function toArray(): array
    {
        return ['name' => $this->name, 'course' => $this->course, 'hours' => $this->hours, 'completed_on' => $this->completedOn, 'provider' => $this->provider, 'confidence' => $this->confidence];
    }
}
