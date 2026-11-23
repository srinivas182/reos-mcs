<?php

declare(strict_types=1);

namespace App\Licences\Certificates;

use RuntimeException;

/** The reading service failed; the person types the details instead. */
final class CertificateUnreadable extends RuntimeException {}
