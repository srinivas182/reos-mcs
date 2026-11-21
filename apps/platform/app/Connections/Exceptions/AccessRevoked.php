<?php

declare(strict_types=1);

namespace App\Connections\Exceptions;

use RuntimeException;

/** The tool refused the connection's tokens: the person has to sign in again. */
final class AccessRevoked extends RuntimeException {}
