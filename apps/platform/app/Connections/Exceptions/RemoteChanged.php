<?php

declare(strict_types=1);

namespace App\Connections\Exceptions;

use RuntimeException;

/**
 * The record changed at the tool since REOS last saw it (Google's 412
 * Precondition Failed on `If-Match`). REOS fetches the tool's version and
 * lets the conflict rule decide.
 */
final class RemoteChanged extends RuntimeException {}
