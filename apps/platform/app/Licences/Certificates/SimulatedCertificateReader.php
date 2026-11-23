<?php

declare(strict_types=1);

namespace App\Licences\Certificates;

use Carbon\CarbonImmutable;

/**
 * Demo certificate reading (B10.3b), so the review can be shown and tested
 * before Textract is connected. Never used in production. Every file reads
 * as a 4-hour Legal Update II certificate from a demo school in the
 * account holder's name, finished three days ago; a file whose name
 * contains "blurry" reads nothing (the person types the details).
 */
final readonly class SimulatedCertificateReader implements CertificateReader
{
    public function __construct(private ?string $fileName = null) {}

    public function id(): string
    {
        return 'simulator';
    }

    public function read(string $bytes, string $mime, ?string $accountName): CertificateFields
    {
        if ($this->fileName !== null && str_contains(strtolower($this->fileName), 'blurry')) {
            return new CertificateFields(null, null, null, null, null);
        }

        return new CertificateFields(
            name: $accountName,
            course: 'Legal Update II: Ethics and Agency',
            hours: 4.0,
            completedOn: CarbonImmutable::now('America/Chicago')->subDays(3)->toDateString(),
            provider: 'Texas Realty Institute (demo)',
            confidence: ['name' => 98.5, 'course' => 97.2, 'hours' => 95.0, 'completed_on' => 93.4, 'provider' => 90.1],
        );
    }
}
