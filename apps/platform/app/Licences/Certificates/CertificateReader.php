<?php

declare(strict_types=1);

namespace App\Licences\Certificates;

/** Reads a certificate's image or one-page PDF into its fields (B10.3b). */
interface CertificateReader
{
    /** `textract` or `simulator`, stored with what it read. */
    public function id(): string;

    /** @throws CertificateUnreadable when the service can't read it now */
    public function read(string $bytes, string $mime, ?string $accountName): CertificateFields;
}
