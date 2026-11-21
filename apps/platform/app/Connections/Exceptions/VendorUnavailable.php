<?php

declare(strict_types=1);

namespace App\Connections\Exceptions;

use RuntimeException;

/** Any other failure talking to the tool (server error, timeout); retried with backoff. */
final class VendorUnavailable extends RuntimeException {}
